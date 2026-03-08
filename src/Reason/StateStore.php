<?php

declare(strict_types=1);

namespace H13\FeedPulse\Reason;

use Ray\Di\Di\Named;

use function array_unique;
use function array_values;
use function date;
use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function in_array;
use function is_dir;
use function json_decode;
use function json_encode;
use function mkdir;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

final class StateStore
{
    private readonly string $path;

    public function __construct(
        #[Named('app_dir')]
        string $appDir,
    ) {
        $this->path = $appDir . '/state/processed.json';
    }

    public function isProcessed(string $url): bool
    {
        $state = $this->load();

        return in_array($url, $state['processedUrls'], true);
    }

    /** @param list<string> $urls */
    public function markProcessed(array $urls): void
    {
        $state = $this->load();
        $state['processedUrls'] = array_values(array_unique([...$state['processedUrls'], ...$urls]));
        $state['lastRun'] = date('c');
        $this->save($state);
    }

    /** @return array{processedUrls: list<string>, lastRun: ?string} */
    private function load(): array
    {
        if (! file_exists($this->path)) {
            return ['processedUrls' => [], 'lastRun' => null];
        }

        $raw = file_get_contents($this->path);
        if ($raw === false) {
            return ['processedUrls' => [], 'lastRun' => null];
        }

        /** @var array{processedUrls: list<string>, lastRun: ?string} $state */
        $state = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return $state;
    }

    /** @param array<string, mixed> $state */
    private function save(array $state): void
    {
        $dir = dirname($this->path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->path, json_encode($state, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }
}
