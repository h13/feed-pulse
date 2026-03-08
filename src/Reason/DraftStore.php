<?php

declare(strict_types=1);

namespace H13\FeedPulse\Reason;

use H13\FeedPulse\Reason\Entity\Draft;
use H13\FeedPulse\Reason\Entity\FeedItem;
use H13\FeedPulse\Reason\Entity\ScoredItem;
use Ray\Di\Di\Named;

use function array_filter;
use function array_map;
use function array_values;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_dir;
use function json_decode;
use function json_encode;
use function mkdir;
use function unlink;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class DraftStore
{
    private readonly string $dir;

    public function __construct(
        #[Named('app_dir')]
        string $appDir,
    ) {
        $this->dir = $appDir . '/state/drafts';
    }

    public function save(Draft $draft): void
    {
        if (! is_dir($this->dir)) {
            mkdir($this->dir, 0755, true);
        }

        $data = [
            'id' => $draft->id,
            'channel' => $draft->channel,
            'content' => $draft->content,
            'item' => [
                'feed' => [
                    'title' => $draft->item->feed->title,
                    'link' => $draft->item->feed->link,
                    'description' => $draft->item->feed->description,
                    'pubDate' => $draft->item->feed->pubDate,
                    'source' => $draft->item->feed->source,
                    'category' => $draft->item->feed->category,
                ],
                'score' => $draft->item->score,
                'matchedTopics' => $draft->item->matchedTopics,
            ],
            'createdAt' => $draft->createdAt,
        ];

        file_put_contents(
            "{$this->dir}/{$draft->id}.json",
            json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
        );
    }

    /** @return list<Draft> */
    public function loadAll(): array
    {
        if (! is_dir($this->dir)) {
            return [];
        }

        $files = glob("{$this->dir}/*.json") ?: [];

        return array_values(array_filter(array_map(
            fn (string $f) => $this->loadFile($f),
            $files,
        )));
    }

    public function delete(string $id): void
    {
        $path = "{$this->dir}/{$id}.json";
        if (! file_exists($path)) {
            return;
        }

        unlink($path);
    }

    public function clear(): void
    {
        $files = glob("{$this->dir}/*.json") ?: [];
        foreach ($files as $file) {
            unlink($file);
        }
    }

    private function loadFile(string $path): Draft|null
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        /** @var array{id: string, channel: string, content: string, item: array{feed: array{title: string, link: string, description: string, pubDate: string, source: string, category: string}, score: float, matchedTopics: list<string>}, createdAt: string} $data */
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return new Draft(
            id: $data['id'],
            channel: $data['channel'],
            content: $data['content'],
            item: new ScoredItem(
                feed: new FeedItem(...$data['item']['feed']),
                score: $data['item']['score'],
                matchedTopics: $data['item']['matchedTopics'],
            ),
            createdAt: $data['createdAt'],
        );
    }
}
