<?php

declare(strict_types=1);

namespace H13\FeedPulse\Llm;

use BEAR\ToolUse\LlmClientInterface;
use BEAR\ToolUse\LlmResponse;
use Ray\Di\Di\Named;

/**
 * Claude API client for BEAR.ToolUse agent loop.
 */
final class ClaudeClient implements LlmClientInterface
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-haiku-4-5-20251001';

    public function __construct(
        #[Named('anthropic_api_key')]
        private readonly string $apiKey,
    ) {
    }

    /**
     * @param list<array{role: string, content: string|list<array<string, mixed>>}> $messages
     * @param list<array{name: string, description: string, input_schema: array<string, mixed>}> $tools
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

        $ch = curl_init(self::API_URL);
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize curl');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                "x-api-key: {$this->apiKey}",
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (! is_string($response) || $httpCode !== 200) {
            throw new \RuntimeException("Claude API error {$httpCode}: {$response}");
        }

        /** @var array{content: list<array{type: string, text?: string, id?: string, name?: string, input?: array<string, mixed>}>, stop_reason: string} $data */
        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        return new LlmResponse(
            content: $data['content'],
            stopReason: $data['stop_reason'],
        );
    }
}
