<?php

declare(strict_types=1);

namespace H13\FeedPulse\Llm;

use H13\FeedPulse\Contract\LlmInterface;
use Ray\Di\Di\Named;

final class ClaudeLlm implements LlmInterface
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-haiku-4-5-20251001';

    public function __construct(
        #[Named('anthropic_api_key')]
        private readonly string $apiKey,
    ) {
    }

    public function generate(string $systemPrompt, string $userPrompt): string
    {
        $ch = curl_init(self::API_URL);
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize curl');
        }

        $payload = json_encode([
            'model' => self::MODEL,
            'max_tokens' => 1024,
            'system' => $systemPrompt,
            'messages' => [['role' => 'user', 'content' => $userPrompt]],
        ], JSON_THROW_ON_ERROR);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
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

        /** @var array{content: list<array{type: string, text?: string}>} $data */
        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        $texts = array_map(
            fn (array $block) => $block['text'] ?? '',
            array_filter($data['content'], fn (array $b) => $b['type'] === 'text'),
        );

        return implode("\n", $texts);
    }
}
