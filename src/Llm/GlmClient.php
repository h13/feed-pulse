<?php

declare(strict_types=1);

namespace H13\FeedPulse\Llm;

use BEAR\ToolUse\Dispatch\ToolCall;
use BEAR\ToolUse\Llm\LlmClientInterface;
use BEAR\ToolUse\Llm\LlmResponse;
use BEAR\ToolUse\Runtime\Message;
use BEAR\ToolUse\Schema\Tool;
use Override;

use function array_map;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/** LlmClientInterface implementation for OpenAI-compatible APIs (GLM/Z.ai) */
final class GlmClient implements LlmClientInterface
{
    private const string MODEL = 'glm-4.7';

    public function __construct(
        private readonly LlmHttpClient $http,
    ) {
    }

    /**
     * @param list<Message> $messages
     * @param list<Tool>    $tools
     */
    #[Override]
    public function chat(string $system, array $messages, array $tools): LlmResponse
    {
        $openAiMessages = [['role' => 'system', 'content' => $system]];

        foreach ($messages as $message) {
            foreach ($this->convertMessage($message->toArray()) as $msg) {
                $openAiMessages[] = $msg;
            }
        }

        /** @var array<string, mixed> $payload */
        $payload = [
            'model' => self::MODEL,
            'max_tokens' => 4096,
            'messages' => $openAiMessages,
        ];

        if ($tools !== []) {
            $payload['tools'] = array_map(
                static fn (Tool $tool): array => [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool->name,
                        'description' => $tool->description,
                        'parameters' => $tool->inputSchema,
                    ],
                ],
                $tools,
            );
        }

        /** @var array{choices: list<array{message: array{content?: string|null, tool_calls?: list<array{id: string, function: array{name: string, arguments: string}}>}, finish_reason: string}>} $data */
        $data = $this->http->request($payload);

        return $this->buildResponse($data);
    }

    /**
     * Convert Anthropic-format message to OpenAI-format message(s)
     *
     * @param array{role: string, content: list<array<string, mixed>>} $message
     *
     * @return list<array<string, mixed>>
     */
    private function convertMessage(array $message): array
    {
        $role = $message['role'];
        $content = $message['content'];

        if ($role === 'user' && isset($content[0]['type']) && $content[0]['type'] === 'tool_result') {
            return $this->convertToolResults($content);
        }

        if ($role === 'user') {
            return [['role' => 'user', 'content' => $this->extractText($content)]];
        }

        if ($role === 'assistant') {
            return [$this->convertAssistant($content)];
        }

        return [['role' => $role, 'content' => json_encode($content, JSON_THROW_ON_ERROR)]];
    }

    /**
     * Convert Anthropic tool_result blocks to OpenAI tool messages
     *
     * @param list<array<string, mixed>> $content
     *
     * @return list<array<string, mixed>>
     */
    private function convertToolResults(array $content): array
    {
        return array_map(
            static function (array $block): array {
                /** @var string $toolUseId */
                $toolUseId = $block['tool_use_id'] ?? '';
                /** @var mixed $resultContent */
                $resultContent = $block['content'] ?? '';

                return [
                    'role' => 'tool',
                    'tool_call_id' => $toolUseId,
                    'content' => is_string($resultContent) ? $resultContent : json_encode($resultContent, JSON_THROW_ON_ERROR),
                ];
            },
            $content,
        );
    }

    /**
     * Convert Anthropic assistant content blocks to OpenAI assistant message
     *
     * @param list<array<string, mixed>> $content
     *
     * @return array<string, mixed>
     */
    private function convertAssistant(array $content): array
    {
        $text = $this->extractText($content);
        $toolCalls = [];

        foreach ($content as $block) {
            if (($block['type'] ?? '') !== 'tool_use' || ! isset($block['id'], $block['name'])) {
                continue;
            }

            /** @var array<string, mixed> $input */
            $input = $block['input'] ?? [];
            $toolCalls[] = [
                'id' => $block['id'],
                'type' => 'function',
                'function' => [
                    'name' => $block['name'],
                    'arguments' => json_encode($input, JSON_THROW_ON_ERROR),
                ],
            ];
        }

        /** @var array<string, mixed> $msg */
        $msg = ['role' => 'assistant'];
        if ($text !== '') {
            $msg['content'] = $text;
        }

        if ($toolCalls !== []) {
            $msg['tool_calls'] = $toolCalls;
        }

        return $msg;
    }

    /**
     * Extract concatenated text from content blocks
     *
     * @param list<array<string, mixed>> $content
     */
    private function extractText(array $content): string
    {
        $text = '';
        foreach ($content as $block) {
            if (($block['type'] ?? '') !== 'text' || ! isset($block['text'])) {
                continue;
            }

            /** @var string $blockText */
            $blockText = $block['text'];
            $text .= $blockText;
        }

        return $text;
    }

    /**
     * Build LlmResponse from OpenAI-format response
     *
     * @param array{choices: list<array{message: array{content?: string|null, tool_calls?: list<array{id: string, function: array{name: string, arguments: string}}>}, finish_reason: string}>} $data
     */
    private function buildResponse(array $data): LlmResponse
    {
        $choice = $data['choices'][0];
        $msg = $choice['message'];
        $finishReason = $choice['finish_reason'];

        $stopReason = match ($finishReason) {
            'tool_calls' => 'tool_use',
            'stop' => 'end_turn',
            'length' => 'max_tokens',
            default => $finishReason,
        };

        /** @var list<array{type: string, text?: string, id?: string, name?: string, input?: array<string, mixed>}> $content */
        $content = [];
        $textContent = $msg['content'] ?? null;
        if (is_string($textContent) && $textContent !== '') {
            $content[] = ['type' => 'text', 'text' => $textContent];
        }

        $toolCalls = [];
        $rawToolCalls = $msg['tool_calls'] ?? [];
        foreach ($rawToolCalls as $tc) {
            /** @var mixed $decoded */
            $decoded = json_decode($tc['function']['arguments'], true, 512, JSON_THROW_ON_ERROR);
            /** @var array<string, mixed> $input */
            $input = is_array($decoded) ? $decoded : [];

            $content[] = [
                'type' => 'tool_use',
                'id' => $tc['id'],
                'name' => $tc['function']['name'],
                'input' => $input,
            ];

            $toolCalls[] = ToolCall::fromArray([
                'id' => $tc['id'],
                'name' => $tc['function']['name'],
                'input' => $input,
            ]);
        }

        return new LlmResponse(
            stopReason: $stopReason,
            content: $content,
            toolCalls: $toolCalls,
        );
    }
}
