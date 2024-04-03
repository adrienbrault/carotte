<?php

namespace App\Model;

use Gioni06\Gpt3Tokenizer\Gpt3Tokenizer;

class MessageFactory
{
    public function __construct(
        private readonly Gpt3Tokenizer $tokenizer,
    ) {

    }

    public function createAssistant(string $content): Message
    {
        return new Message(
            'assistant',
            $content,
            $this->tokenizer->count($content),
        );
    }

    public function createUser(string $content): Message
    {
        return new Message(
            'user',
            $content,
            $this->tokenizer->count($content),
        );
    }
}