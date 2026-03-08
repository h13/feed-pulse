<?php

declare(strict_types=1);

namespace H13\FeedPulse\Reason;

use H13\FeedPulse\Reason\Entity\ScoredItem;
use Ray\Di\Di\Named;

use function array_map;
use function file_get_contents;
use function glob;
use function implode;
use function is_dir;
use function is_int;
use function is_string;
use function str_replace;
use function ucfirst;

final class PromptBuilder
{
    private readonly string $promptsDir;

    public function __construct(
        #[Named('app_dir')]
        string $appDir,
    ) {
        $this->promptsDir = $appDir . '/prompts';
    }

    /** @param array<string, mixed> $persona */
    public function buildSystemPrompt(array $persona): string
    {
        $voice = file_get_contents("{$this->promptsDir}/voice.md") ?: '';
        $examples = $this->loadExamples();

        $parts = [$voice, '', '## Channel Settings'];

        foreach (['tone', 'style', 'language'] as $key) {
            $value = $persona[$key] ?? null;
            if (! is_string($value) && ! is_int($value)) {
                continue;
            }

            $parts[] = ucfirst($key) . ': ' . (string) $value;
        }

        /** @var int|string|null $maxLength */
        $maxLength = $persona['max_length'] ?? null;
        if (is_int($maxLength) || is_string($maxLength)) {
            $parts[] = 'Max length: ' . (string) $maxLength . ' characters';
        }

        $parts[] = '';
        $parts[] = '## Writing Examples (match this voice)';
        $parts[] = $examples;

        return implode("\n", $parts);
    }

    public function buildUserPrompt(string $type, ScoredItem $item): string
    {
        $promptName = $type === 'wordpress' ? 'blog-article' : 'sns-post';
        $template = file_get_contents("{$this->promptsDir}/{$promptName}.md") ?: '';

        return str_replace(
            ['{{title}}', '{{description}}', '{{link}}', '{{topics}}'],
            [
                $item->feed->title,
                $item->feed->description,
                $item->feed->link,
                implode(', ', $item->matchedTopics),
            ],
            $template,
        );
    }

    private function loadExamples(): string
    {
        $dir = "{$this->promptsDir}/examples";
        if (! is_dir($dir)) {
            return '';
        }

        $files = glob("{$dir}/*.md") ?: [];

        return implode(
            "\n\n---\n\n",
            array_map(static fn (string $f) => file_get_contents($f) ?: '', $files),
        );
    }
}
