<?php

declare(strict_types=1);

namespace H13\FeedPulse\Reason;

use H13\FeedPulse\Contract\LlmInterface;
use H13\FeedPulse\Reason\Entity\Draft;
use H13\FeedPulse\Reason\Entity\ScoredItem;
use Symfony\Component\Yaml\Yaml;

final class Generator
{
    private readonly string $promptsDir;
    private readonly string $channelsDir;

    public function __construct(
        private readonly LlmInterface $llm,
    ) {
        $this->promptsDir = dirname(__DIR__, 2) . '/prompts';
        $this->channelsDir = dirname(__DIR__, 2) . '/config/channels';
    }

    /**
     * @param list<ScoredItem> $items
     * @return list<Draft>
     */
    public function generate(array $items): array
    {
        $channels = $this->loadEnabledChannels();
        if ($channels === []) {
            return [];
        }

        $drafts = [];
        foreach ($channels as $channel) {
            $limit = $channel['channel']['publish']['max_per_day'] ?? 5;
            $targets = array_slice($items, 0, $limit);

            foreach ($targets as $item) {
                $drafts[] = $this->generateForChannel($channel, $item);
            }
        }

        return $drafts;
    }

    /** @param array<string, mixed> $channel */
    private function generateForChannel(array $channel, ScoredItem $item): Draft
    {
        $systemPrompt = $this->buildSystemPrompt($channel['channel']['persona'] ?? []);
        $userPrompt = $this->buildUserPrompt($channel['channel']['type'] ?? 'x', $item);

        $content = $this->llm->generate($systemPrompt, $userPrompt);
        $channelName = $channel['channel']['name'] ?? 'unknown';

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
                if (isset($persona[$key])) {
                    $parts[] = ucfirst($key) . ": {$persona[$key]}";
                }
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
            array_map(fn (string $f) => file_get_contents($f) ?: '', $files),
        );
    }

    private function toDraftId(string $channel, ScoredItem $item): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($item->feed->title)) ?? '';
        $slug = substr($slug, 0, 60);

        return "{$channel}-{$slug}";
    }

    /** @return list<array<string, mixed>> */
    private function loadEnabledChannels(): array
    {
        $files = glob("{$this->channelsDir}/*.yaml") ?: [];

        $channels = array_map(
            fn (string $f) => Yaml::parseFile($f),
            $files,
        );

        return array_values(array_filter(
            $channels,
            fn (mixed $c) => is_array($c) && ($c['channel']['enabled'] ?? false),
        ));
    }
}
