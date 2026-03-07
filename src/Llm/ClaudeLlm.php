<?php

declare(strict_types=1);

namespace H13\FeedPulse\Llm;

use H13\FeedPulse\Contract\LlmInterface;

use function array_filter;
use function array_map;
use function implode;

final class ClaudeLlm implements LlmInterface
{
    private const string MODEL = 'claude-haiku-4-5-20251001';

    public function __construct(
        private readonly ClaudeHttpClient $http,
    ) {
    }

    public function generate(string $systemPrompt, string $userPrompt): string
    {
        /** @var array{content: list<array{type: string, text?: string}>} $data */
        $data = $this->http->request([
            'model' => self::MODEL,
            'max_tokens' => 1024,
            'system' => $systemPrompt,
            'messages' => [['role' => 'user', 'content' => $userPrompt]],
        ]);

        $texts = array_map(
            static fn (array $block) => $block['text'] ?? '',
            array_filter($data['content'], static fn (array $b) => $b['type'] === 'text'),
        );

        return implode("\n", $texts);
    }
}
