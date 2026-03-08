<?php

declare(strict_types=1);

namespace H13\FeedPulse\Llm;

use H13\FeedPulse\Contract\LlmInterface;
use Override;

final class GlmLlm implements LlmInterface
{
    private const string MODEL = 'glm-4.7';

    public function __construct(
        private readonly OpenAiHttpClient $http,
    ) {
    }

    #[Override]
    public function generate(string $systemPrompt, string $userPrompt): string
    {
        /** @var array{choices: list<array{message: array{content: string}}>} $data */
        $data = $this->http->request([
            'model' => self::MODEL,
            'max_tokens' => 1024,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        return $data['choices'][0]['message']['content'] ?? '';
    }
}
