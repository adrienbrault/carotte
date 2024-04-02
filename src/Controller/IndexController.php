<?php

namespace App\Controller;

use OpenAI\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Turbo\TurboBundle;
use function Psl\Vec\map_with_key;

class IndexController extends AbstractController
{
    #[Route('/', name: 'app_index')]
    public function index(
        Request $request,
        Client $openAIClient,
        HubInterface $hub,
        EventDispatcherInterface $eventDispatcher
    ): Response
    {
        $messages = [];

        $form = $this->createMessagesForm();
        if ($form->handleRequest($request)->isSubmitted() && $form->isValid()) {
            $messages = $form['messages']->getData();
            $newMessage = $form['new_message']->getData();

            $hub->publish(new Update(
                'chat',
                $this->renderView('index/message.stream.html.twig', [
                    'message' => $newMessage,
                    'index' => count($messages),
                ])
            ));

            $messages[] = $newMessage;

            $chatRequest = [
                'model' => 'adrienbrault/nous-hermes2pro:Q4_K_M',
                'messages' => map_with_key(
                    $messages,
                    fn (int $key, string $message) => [
                        'role' => $key % 2 === 0 ? 'user' : 'assistant',
                        'content' => $message
                    ],
                ),
                'max_tokens' => 50,
            ];

            $messageIndex = count($messages);

            $chatRequest['stream'] = true;
            $streamResponse = $openAIClient->chat()->createStreamed($chatRequest);

            $fullMessage = '';

            foreach ($streamResponse as $response) {
                $deltaContent = $response->choices[0]->delta->content;

                $hub->publish(new Update(
                    'ai_reply',
                    $this->renderView('index/message_chunk.stream.html.twig', [
                        'message' => $deltaContent,
                        'index' => $messageIndex,
                    ])
                ));
                $fullMessage .= $deltaContent;
            }

            $messages[] = $fullMessage;

            $form = $this->createMessagesForm($messages);
        }

        return $this->render('index/index.html.twig', [
            'form' => $form,
            'messages' => $messages,
        ]);
    }

    public function createMessagesForm(array $messages = []): \Symfony\Component\Form\FormInterface
    {
        return $this->createFormBuilder(['messages' => $messages], [
            'method' => 'GET',
        ])
            ->add('messages', CollectionType::class, [
                'entry_type' => HiddenType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'delete_empty' => true,
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
