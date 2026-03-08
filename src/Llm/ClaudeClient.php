<?php

declare(strict_types=1);

namespace H13\FeedPulse\Llm;

use BEAR\ToolUse\Dispatch\ToolCall;
use BEAR\ToolUse\Llm\LlmClientInterface;
use BEAR\ToolUse\Llm\LlmResponse;
use BEAR\ToolUse\Runtime\Message;
use BEAR\ToolUse\Schema\Tool;
use Override;

use function array_filter;
use function array_map;
use function array_values;

final class ClaudeClient implements LlmClientInterface
{
    private const string MODEL = 'claude-haiku-4-5-20251001';

    public function __construct(
        private readonly ClaudeHttpClient $http,
    ) {
    }

    /**
     * @param list<Message> $messages
     * @param list<Tool>    $tools
     */
    #[Override]
    public function chat(string $system, array $messages, array $tools): LlmResponse
    {
        $payload = [
            'model' => self::MODEL,
            'max_tokens' => 4096,
            'system' => $system,
            'messages' => array_map(static fn (Message $m) => $m->toArray(), $messages),
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
        }

        /** @var array{content: list<array{type: string, text?: string, id?: string, name?: string, input?: array<string, mixed>}>, stop_reason: string} $data */
        $data = $this->http->request($payload);

        $toolCalls = array_values(array_filter(array_map(
            static fn (array $block): ToolCall|null => $block['type'] === 'tool_use' && isset($block['id'], $block['name'])
                ? ToolCall::fromArray(['id' => $block['id'], 'name' => $block['name'], 'input' => $block['input'] ?? []])
                : null,
            $data['content'],
        )));

        return new LlmResponse(
            stopReason: $data['stop_reason'],
            content: $data['content'],
            toolCalls: $toolCalls,
        );
    }
}
