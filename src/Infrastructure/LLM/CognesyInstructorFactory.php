<?php

namespace App\Infrastructure\LLM;

use Cognesy\Instructor\Clients\OpenAI\OpenAIClient;
use Cognesy\Instructor\Instructor;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CognesyInstructorFactory
{
    public function __construct(
        #[Autowire('%env(OLLAMA_HOST)%')]
        private readonly string $ollamaHost,
    ) {

    }

    public function create(): Instructor
    {
        return (new Instructor())
            ->withClient(new OpenAIClient(
                'sk-xxx',
                $this->ollamaHost . '/v1'
            ))
        ;
    }
}
