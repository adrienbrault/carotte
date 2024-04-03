<?php

namespace App\Model;

use Gioni06\Gpt3Tokenizer\Gpt3Tokenizer;
use function Psl\Json\encode;

class MessageFactory
{
    public function createAssistant(string $content): Message
    {
        return new Message(
            'assistant',
            $content,
        );
    }

    public function createUser(string $content): ?Message
    {
        if ($content === '') {
            return null;
        }

        return new Message(
            'user',
            $content,
        );
    }

    public function createH2ProSystem(string $content, array $tools): Message
    {
        $content = implode("\n", [
            'You are a function calling AI model.',
            'You are provided with function signatures within <tools></tools> XML tags.',
            'You may call one or more functions to assist with the user query.',
            "Don't make assumptions about what values to plug into functions.",
            'Here are the available tools:',
            '```',
            '<tools>',
            encode($tools),
            '</tools>',
            '```',
            'Use the following json schema for each tool call you will make:',
            '```',
            '{"type": "object", "properties": {"name": {"title": "Name", "type": "string"}, "arguments": {"title": "Arguments", "type": "object"}}, "required": ["arguments", "name"], "title": "FunctionCall"}',
            '```',
            'For each function call return a json object with function name and arguments within `<tool_call></tool_call>` XML tags as follows:',
            '```',
            '<tool_call>',
            '{"name": <function-name>, "arguments": <args-dict>}',
            '</tool_call>',
            '```',
            $content
        ]);

        return new Message(
            'system',
            $content,
        );
    }
}