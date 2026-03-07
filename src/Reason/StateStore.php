<?php

declare(strict_types=1);

namespace H13\FeedPulse\Reason;

final class StateStore
{
    private readonly string $path;

    public function __construct()
    {
        $this->path = dirname(__DIR__, 2) . '/state/processed.json';
    }

    public function isProcessed(string $url): bool
    {
        $state = $this->load();

        return in_array($url, $state['processedUrls'], true);
    }

    /**
     * @param list<string> $urls
     */
    public function markProcessed(array $urls): void
    {
        $state = $this->load();
        $state['processedUrls'] = array_values(array_unique([...$state['processedUrls'], ...$urls]));
        $state['lastRun'] = date('c');
        $this->save($state);
    }

    /**
     * @return array{processedUrls: list<string>, lastRun: ?string}
     */
    private function load(): array
    {
        if (! file_exists($this->path)) {
            return ['processedUrls' => [], 'lastRun' => null];
        }

        $raw = file_get_contents($this->path);
        if ($raw === false) {
            return ['processedUrls' => [], 'lastRun' => null];
        }

        /** @var array{processedUrls: list<string>, lastRun: ?string} */
        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $state
     */
    private function save(array $state): void
    {
        $dir = dirname($this->path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }
}
