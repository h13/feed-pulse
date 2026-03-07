<?php

declare(strict_types=1);

namespace H13\FeedPulse\Reason;

use H13\FeedPulse\Reason\Entity\PublishResult;
use Ray\Di\Di\Named;

use function array_map;
use function date;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_dir;
use function json_decode;
use function json_encode;
use function mkdir;
use function rsort;

final class HistoryStore
{
    private readonly string $dir;

    public function __construct(
        #[Named('app_dir')]
        string $appDir,
    ) {
        $this->dir = $appDir . '/state/history';
    }

    /** @param list<PublishResult> $results */
    public function save(array $results): void
    {
        if (! is_dir($this->dir)) {
            mkdir($this->dir, 0755, true);
        }

        $data = [
            'publishedAt' => date('c'),
            'results' => array_map(fn (PublishResult $r) => [
                'channel' => $r->channel,
                'title' => $r->title,
                'url' => $r->url,
                'error' => $r->error,
                'publishedAt' => $r->publishedAt,
            ], $results),
        ];

        $date = date('Y-m-d');
        file_put_contents(
            "{$this->dir}/{$date}.json",
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
        );
    }

    /** @return list<array<string, mixed>> */
    public function loadAll(): array
    {
        if (! is_dir($this->dir)) {
            return [];
        }

        $files = glob("{$this->dir}/*.json") ?: [];
        rsort($files);

        $history = [];
        foreach ($files as $file) {
            $raw = file_get_contents($file);
            if ($raw === false) {
                continue;
            }
            $history[] = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        }

        return $history;
    }
}
