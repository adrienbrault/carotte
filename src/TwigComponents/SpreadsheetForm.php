<?php

namespace App\TwigComponents;
use App\Entity\Spreadsheet;
use App\Form\SpreadsheetType;
use App\Infrastructure\Instructor\MySequence;
use App\Model\Column;
use App\Model\ColumnType;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Sequences\Sequence;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Utils\JsonParser;
use OpenAI\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;
use function Psl\Json\encode;

#[AsLiveComponent]
class SpreadsheetForm extends AbstractController
{
    public function __construct(
        private readonly Instructor $instructor,
        private readonly HubInterface $hub,
        private readonly Client $openAIClient
    ) { }

    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public ?string $initialContext = null;

    #[LiveProp]
    public ?array $list = null;

    protected function instantiateForm(): FormInterface
    {
        $initialSpreadsheet = new Spreadsheet(
            [
                new Column(
                    'Username',
                    'The username.',
                    ColumnType::TEXT
                ),
                new Column(
                    'Comment',
                    'How does this user appears in the context',
                    ColumnType::TEXT
                ),
            ],
            $this->initialContext ?? ''
        );
        if ($this->initialContext !== null) {
            $initialSpreadsheet->autoExtract = true;
        }

        return $this->createForm(SpreadsheetType::class, $initialSpreadsheet);
    }

    #[LiveAction]
    public function save(): void
    {
        $this->submitForm();
        $spreadsheet = $this->form->getData();
        assert($spreadsheet instanceof Spreadsheet);

//        $this->list = $this->getListWithInstructor(
        $this->list = $this->getListWithOpenAI(
            $spreadsheet,
            'adrienbrault/nous-hermes2pro:Q5_K_M',
            fn (?array $list) => $this->onPartialListUpdate($spreadsheet, $list)
        );
    }

    private function onPartialListUpdate(Spreadsheet $spreadsheet, ?array $list): void
    {
        $this->hub->publish(new Update(
            'spreadsheet',
            $this->renderView('components/SpreadsheetForm.stream.html.twig', [
                'spreadsheet' => $spreadsheet,
                'list' => $list ?? [],
            ])
        ));
    }

    private function getListWithInstructor(
        Spreadsheet $spreadsheet,
        string $model,
        callable $onPartialUpdate
    ): array {
        return $this->instructor
            ->request(
                $spreadsheet->context,
                new MySequence($spreadsheet),
                $model,
                mode: Mode::Json,
                options: [
                    'stream' => true,
                ],
            )
            ->onPartialUpdate(fn ($mySequence) => is_array($mySequence->list) ? $onPartialUpdate($mySequence->list) : null)
            ->get()->list
        ;
    }

    public function getListWithOpenAI(
        Spreadsheet $spreadsheet,
        string $model,
        callable $onPartialUpdate
    ): array {
        $jsonSchema = MySequence::createSchema($spreadsheet->columns)->toArray();
        unset($jsonSchema['$comment']);

        $chatRequest = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->getHermes2ProJsonPrompt(
                        encode($jsonSchema)
                    ) . "\nExtract information from the user message following the schema."
                ],
                [
                    'role' => 'user',
                    'content' => $spreadsheet->context,
                ]
            ],
            'stream' => true,
            'response_format' => [
                'type' => 'json_object'
            ],
        ];

        $streamResponse = $this->openAIClient->chat()->createStreamed($chatRequest);

        $jsonParser = new JsonParser;

        $content = '';
        $data = null;
        foreach ($streamResponse as $response) {
            $deltaContent = $response->choices[0]->delta->content;
            $content .= $deltaContent;

            $list = $jsonParser->parse($content)['list'] ?? null;

            if (is_array($list)) {
                $onPartialUpdate($list);
            }
        }

        return $data ?? [];
    }

    private function getHermes2ProJsonPrompt(string $schema): string
    {
        return <<<PROMPT
You are a helpful assistant that answers in JSON.
Here's the json schema you must adhere to:
<schema>
{$schema}
</schema>
PROMPT;

    }
}
