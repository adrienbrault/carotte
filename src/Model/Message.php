<?php

namespace App\Model;

use function Psl\Json\encode;
use function Psl\Vec\filter_nulls;

class Message
{
    public function __construct(
        public string $role,
        public ?string $content,
        /**
         * @var list<array{name: string, arguments: array}>
         */
        public array $toolCalls = [],
        /**
         * @var list<array{name: string, content: mixed}>
         */
        public array $toolResponses = [],
        public \DateTime $createdAt = new \DateTime(),
        public ?\DateTime $completedAt = null,
    ) {
        $this->completedAt = $this->completedAt ?? $this->createdAt;
    }

    public function withAddedContent(string $newContent): self
    {
        return new self(
            $this->role,
            $this->content . $newContent,
            $this->toolCalls,
            $this->toolResponses,
            $this->createdAt,
            new \DateTime(),
        );
    }

    public function withToolCall(string $name, array $arguments, string $newContent): self
    {
        $toolCalls = [
            ...$this->toolCalls,
            ['name' => $name, 'arguments' => $arguments]
        ];

        return new self(
            $this->role,
            $newContent,
            $toolCalls,
            $this->toolResponses,
            $this->createdAt,
            new \DateTime(),
        );
    }

    public function withToolResponse(string $name, mixed $content): self
    {
        $toolResponses = [
            ...$this->toolResponses,
            ['name' => $name, 'content' => $content]
        ];

        return new self(
            $this->role,
            $this->content,
            $this->toolCalls,
            $toolResponses,
            $this->createdAt,
            new \DateTime(),
        );
    }

    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'content' => \Psl\Str\join(
                filter_nulls(
                    [
                        $this->content,
                        ...\Psl\Vec\map(
                            $this->toolCalls,
                            fn(array $toolCall) => sprintf(
                                '<tool_call>%s</tool_call>',
                                encode($toolCall)
                            )
                        ),
                        ...\Psl\Vec\map(
                            $this->toolResponses,
                            fn(array $toolResponse) => sprintf(
                                '<tool_response>%s</tool_response>',
                                encode($toolResponse)
                            )
                        ),
                    ]
                ),
                "\n"
            ),
        ];
    }
}
