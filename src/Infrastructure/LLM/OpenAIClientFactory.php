<?php

namespace App\Infrastructure\LLM;

use OpenAI\Client;
use OpenAI\Factory;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class OpenAIClientFactory
{
    public function __construct(
        #[Autowire('%env(OLLAMA_HOST)%')]
        private readonly string $ollamaHost,
        private readonly ClientInterface $httpClient,
    ) {

    }

    public function create(): Client
    {
        return (new Factory)
            ->withBaseUri($this->ollamaHost . '/v1')
            ->withHttpClient($this->httpClient)
            ->make()
        ;
    }
}
