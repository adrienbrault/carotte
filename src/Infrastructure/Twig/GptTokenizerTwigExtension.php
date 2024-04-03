<?php

namespace App\Infrastructure\Twig;

use Gioni06\Gpt3Tokenizer\Gpt3Tokenizer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class GptTokenizerTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly Gpt3Tokenizer $tokenizer,
    ) { }

    public function getFilters()
    {
        return [
            new TwigFilter('gpt_token_count', [$this->tokenizer, 'count']),
        ];
    }

}