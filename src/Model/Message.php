<?php

namespace App\Model;

class Message
{
    public function __construct(
        public string $role,
        public string $content,
        public int $tokens = 0,
    ) {
    }
}