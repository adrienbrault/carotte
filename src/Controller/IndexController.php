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
use function Psl\Vec\map;
use function Psl\Vec\map_with_key;

class IndexController extends AbstractController
{
    #[Route('/', name: 'app_index')]
    public function index(
        Request $request,
        Client $openAIClient,
        HubInterface $hub,
        EventDispatcherInterface $eventDispatcher,
        SerializerInterface $serializer,
        MessageFactory $messageFactory,
        FormFactoryInterface $formFactory,
    ): Response
    {
        $messages = [];

        $form = $this->createMessagesForm($formFactory, $messages);
        if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            $messages = $form['messages']->getData();
            $newMessage = $messageFactory->createUser(
                $form['new_message']->getData() ?? ''
            );

            if ($newMessage->tokens > 0) {
                $hub->publish(new Update(
                    'chat',
                    $this->renderView('index/message.stream.html.twig', [
                        'message' => $newMessage,
                    ])
                ));

                $messages[] = $newMessage;

                $hub->publish(new Update(
                    'chat',
                    $this->renderView('index/add_reply.stream.html.twig', [
                        'message' => $messageFactory->createAssistant(''),
                    ])
                ));

                $chatRequest = [
                    'model' => 'adrienbrault/nous-hermes2pro:Q4_K_M',
                    'messages' => map(
                        $messages,
                        fn (Message $message) => [
                            'role' => $message->role,
                            'content' => $message->content,
                        ],
                    ),
                    'max_tokens' => 250,
                ];

                $chatRequest['stream'] = true;
                $streamResponse = $openAIClient->chat()->createStreamed($chatRequest);

                $fullMessage = $messageFactory->createAssistant('');

                foreach ($streamResponse as $response) {
                    $deltaContent = $response->choices[0]->delta->content;

                    $fullMessage = $messageFactory->createAssistant(
                        $fullMessage->content . $deltaContent
                    );

                    $hub->publish(new Update(
                        'ai_reply',
                        $this->renderView('index/message_chunk.stream.html.twig', [
                            'message' => $deltaContent,
                            'full_message' => $fullMessage,
                        ])
                    ));

                }

                $messages[] = $fullMessage;

                $form = $this->createMessagesForm($formFactory, $messages);
            }
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
            ->add('send', SubmitType::class)
            ->getForm()
        ;
    }
}
