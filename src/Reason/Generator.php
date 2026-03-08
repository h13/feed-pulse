<?php

declare(strict_types=1);

namespace H13\FeedPulse\Reason;

use H13\FeedPulse\Contract\LlmInterface;
use H13\FeedPulse\Reason\Entity\Draft;
use H13\FeedPulse\Reason\Entity\ScoredItem;
use Ray\Di\Di\Named;

use function array_map;
use function date;
use function file_get_contents;
use function glob;
use function implode;
use function is_array;
use function is_dir;
use function preg_replace;
use function str_replace;
use function strtolower;
use function substr;
use function ucfirst;

final class Generator
{
    private readonly string $promptsDir;

    public function __construct(
        private readonly LlmInterface $llm,
        #[Named('app_dir')]
        string $appDir,
    ) {
        $this->promptsDir = $appDir . '/prompts';
    }

    /** @param array<string, mixed> $channelConfig */
    public function generate(ScoredItem $item, array $channelConfig): Draft
    {
        $persona = $channelConfig['persona'] ?? [];
        /** @var string $type */
        $type = $channelConfig['type'] ?? 'x';
        /** @var string $channelName */
        $channelName = $channelConfig['name'] ?? 'unknown';

        $systemPrompt = $this->buildSystemPrompt($persona);
        $userPrompt = $this->buildUserPrompt($type, $item);
        $content = $this->llm->generate($systemPrompt, $userPrompt);

        return new Draft(
            id: $this->toDraftId($channelName, $item),
            channel: $channelName,
            content: $content,
            item: $item,
            createdAt: date('c'),
        );
    }

    private function buildSystemPrompt(mixed $persona): string
    {
        $voice = file_get_contents("{$this->promptsDir}/voice.md") ?: '';
        $examples = $this->loadExamples();

        $parts = [$voice, '', '## Channel Settings'];

        if (is_array($persona)) {
            foreach (['tone', 'style', 'language'] as $key) {
                if (! isset($persona[$key])) {
                    continue;
                }

                $parts[] = ucfirst($key) . ": {$persona[$key]}";
            }

            if (isset($persona['max_length'])) {
                $parts[] = "Max length: {$persona['max_length']} characters";
            }
        }

        $parts[] = '';
        $parts[] = '## Writing Examples (match this voice)';
        $parts[] = $examples;

        return implode("\n", $parts);
    }

    private function buildUserPrompt(string $type, ScoredItem $item): string
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

    private function toDraftId(string $channel, ScoredItem $item): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($item->feed->title)) ?? '';
        $slug = substr($slug, 0, 60);

        return "{$channel}-{$slug}";
    }
}
