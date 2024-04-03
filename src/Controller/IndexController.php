<?php

namespace App\Controller;

use App\Form\MessageType;
use App\Model\Message;
use App\Model\MessageFactory;
use Gioni06\Gpt3Tokenizer\Gpt3Tokenizer;
use OpenAI\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\UX\Turbo\TurboBundle;
use function Psl\Regex\every_match;
use function Psl\Regex\matches;
use function Psl\Regex\replace;
use function Psl\Str\ends_with;
use function Psl\Vec\map;
use function Psl\Vec\map_with_key;

class IndexController extends AbstractController
{
    public function __construct(
        private readonly Client $openAIClient,
        private readonly MessageFactory $messageFactory,
        private readonly HubInterface $hub,
        private readonly Gpt3Tokenizer $tokenizer,
    ) { }

    #[Route('/', name: 'app_index')]
    public function index(
        Request $request,
        HubInterface $hub,
        EventDispatcherInterface $eventDispatcher,
        SerializerInterface $serializer,
        MessageFactory $messageFactory,
        FormFactoryInterface $formFactory,
    ): Response
    {
        $messages = [
            $messageFactory->createH2ProSystem(
                'You are a helpful assistant.',
                $this->createTools()
            ),
        ];

        $form = $this->createMessagesForm($formFactory, $messages);
        if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            $messages = $form['messages']->getData();
            $newMessage = $messageFactory->createUser(
                $form['new_message']->getData() ?? ''
            );

            $messages = $this->getMessages(
                $newMessage,
                $messages,
                $form['model']->getData()
            );

            $form = $this->createMessagesForm($formFactory, $messages);
        }

        return $this->render('index/index.html.twig', [
            'form' => $form,
            'messages' => $messages,
        ]);
    }

    public function createMessagesForm(FormFactoryInterface $formFactory, array $messages = []): \Symfony\Component\Form\FormInterface
    {
        $formBuilder = $formFactory->createNamedBuilder('', data: ['messages' => $messages], options: [
            'method' => 'GET',
        ]);
        return $formBuilder
            ->add('messages', CollectionType::class, [
                'entry_type' => MessageType::class,
                'entry_options' => [
                    'label' => false,
                ],
                'allow_add' => true,
                'allow_extra_fields' => true,
                'label' => false,
            ])
            ->add('new_message', TextType::class, [
                'required' => false,
                'attr' => [
                    'autofocus' => true,
                ],
            ])
            ->add('model', ChoiceType::class, [
                'choices' => [
                    'nous-hermes2pro' => 'adrienbrault/nous-hermes2pro:Q4_K_M',
                    'qwen1.5-0.5b' => 'brittlewis12/Qwen1.5-0.5B-OpenHermes-2.5-GGUF:Q5_K_M',
                    'codellama:13b' => 'codellama:13b-instruct',
                    'deepseek-coder:6.7b' => 'deepseek-coder:6.7b-instruct-q6_K',
                ],
                'required' => true,
            ])
            ->add('send', SubmitType::class)
            ->getForm()
        ;
    }

    private function createTools(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_current_time',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'timezone' => [
                                'type' => 'string',
                                'description' => 'The timezone to get the current time for. Example: "America/Los_Angeles" or "Asia/Tokyo"',
                            ],
                        ],
                        'required' => ['timezone'],
                    ],
                ],
            ],
        ];
    }

    private function inference(
        string $model,
        array $messages
    ): iterable {
        $chatRequest = [
            'model' => $model,
            'messages' => map(
                $messages,
                fn (Message $message) => $message->toArray(),
            ),
            'max_tokens' => 250,
        ];

        $chatRequest['stream'] = true;
        $streamResponse = $this->openAIClient->chat()->createStreamed($chatRequest);

        $fullMessage = $this->messageFactory->createAssistant('');

        foreach ($streamResponse as $response) {
            $deltaContent = $response->choices[0]->delta->content;

            $fullMessage = $fullMessage->withAddedContent($deltaContent);

            if (!matches($fullMessage->content, '#<($|tool($|_($|call($|>))))#')) {
                if ($fullMessage->toolCalls !== []) {
                    // model has invoked a tool, but has not stopped
                    // so we ignore whatever it completes

                    continue;
                }

                yield $fullMessage;
            }

            $toolCallPattern = '#<tool_call>\s*(?P<json>[{\["].+)\s*</tool_call>#s';
            $matches = every_match(
                $fullMessage->content,
                $toolCallPattern,
            );

            if (null === $matches) {
                continue;
            }

            foreach ($matches as $match) {
                $toolCall = json_decode($match['json'], true);

                $fullMessage = $fullMessage->withToolCall(
                    $toolCall['name'],
                    $toolCall['arguments'],
                    replace(
                        $fullMessage->content,
                        $toolCallPattern,
                        '',
                    ),
                );

                // display tool call
                yield $fullMessage;

                // invoke tool
                if ($toolCall['name'] === 'get_current_time') {
                    $fullMessage = $fullMessage->withToolResponse(
                        $toolCall['name'],
                        (new \DateTime('now', new \DateTimeZone($toolCall['arguments']['timezone'])))->format('H:i:s')
                    );
                }

                // display tool call
                yield $fullMessage;
            }
        }
    }

    /**
     * @param list<Message> $messages
     * @return list<Message>
     */
    private function getMessages(
        ?Message $newMessage,
        array $messages,
        string $model
    ): array {
        if (null === $newMessage) {
            return $messages;
        }

        $this->hub->publish(new Update(
            'chat',
            $this->renderView('index/message.stream.html.twig', [
                'message' => $newMessage,
                'message_index' => count($messages),
            ])
        ));

        $messages[] = $newMessage;

        $lastUpdate = null;
        do {
            $this->hub->publish(new Update(
                'chat',
                $this->renderView('index/message.stream.html.twig', [
                    'message_index' => count($messages),
                    'message' => $this->messageFactory->createAssistant(''),
                ])
            ));

            foreach ($this->inference($model, $messages) as $update) {
                $this->hub->publish(new Update(
                    'chat',
                    $this->renderView('index/message_chunk.stream.html.twig', [
                        'message_index' => count($messages),
                        'full_message' => $update,
                        'tokens' => $this->tokenizer->count($update->content),
                    ])
                ));
                $lastUpdate = $update;
            }

            $messages[] = $lastUpdate;
        } while ($lastUpdate !== null && $lastUpdate->toolResponses !== []);

        return $messages;
    }
}
