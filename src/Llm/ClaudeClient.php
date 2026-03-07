<?php

declare(strict_types=1);

namespace H13\FeedPulse\Llm;

use BEAR\ToolUse\LlmClientInterface;
use BEAR\ToolUse\LlmResponse;

final class ClaudeClient implements LlmClientInterface
{
    private const string MODEL = 'claude-haiku-4-5-20251001';

    public function __construct(
        private readonly ClaudeHttpClient $http,
    ) {
    }

    /**
     * @param list<array{role: string, content: string|list<array<string, mixed>>}> $messages
     * @param list<array{name: string, description: string, input_schema: array<string, mixed>}>  $tools
     */
    public function chat(array $messages, array $tools = []): LlmResponse
    {
        $payload = [
            'model' => self::MODEL,
            'max_tokens' => 4096,
            'messages' => $messages,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
        }

        /** @var array{content: list<array{type: string, text?: string, id?: string, name?: string, input?: array<string, mixed>}>, stop_reason: string} $data */
        $data = $this->http->request($payload);

        return new LlmResponse(
            content: $data['content'],
            stopReason: $data['stop_reason'],
        );
    }
}
