<?php

declare(strict_types=1);

namespace H13\FeedPulse\Contract;

interface LlmInterface
{
    public function generate(string $systemPrompt, string $userPrompt): string;
}
