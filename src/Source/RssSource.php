<?php

declare(strict_types=1);

namespace H13\FeedPulse\Source;

use H13\FeedPulse\Contract\SourceInterface;
use H13\FeedPulse\Reason\Entity\FeedItem;
use Laminas\Feed\Reader\Reader;
use Ray\Di\Di\Named;
use Symfony\Component\Yaml\Yaml;
use Throwable;

use function error_log;
use function is_array;
use function strip_tags;

final class RssSource implements SourceInterface
{
    private readonly string $configPath;

    public function __construct(
        #[Named('app_dir')]
        string $appDir,
    ) {
        $this->configPath = $appDir . '/config/sources.yaml';
    }

    /** @return list<FeedItem> */
    public function fetch(): array
    {
        $config = Yaml::parseFile($this->configPath);
        assert(is_array($config));

        /** @var list<array{name: string, url: string, category: string}> $sources */
        $sources = $config['sources'] ?? [];
        $items = [];

        foreach ($sources as $source) {
            $items = [...$items, ...$this->fetchFeed($source)];
        }

        return $items;
    }

    /**
     * @param array{name: string, url: string, category: string} $source
     *
     * @return list<FeedItem>
     */
    private function fetchFeed(array $source): array
    {
        try {
            $feed = Reader::import($source['url']);
        } catch (Throwable $e) {
            error_log("Failed to fetch {$source['name']}: {$e->getMessage()}");

            return [];
        }

        $items = [];
        foreach ($feed as $entry) {
            /** @var \Laminas\Feed\Reader\Entry\EntryInterface $entry */
            $items[] = new FeedItem(
                title: $entry->getTitle() ?? '',
                link: $entry->getLink() ?? '',
                description: strip_tags($entry->getDescription() ?? ''),
                pubDate: $entry->getDateCreated()?->format('c') ?? '',
                source: $source['name'],
                category: $source['category'],
            );
        }

        return $items;
    }
}
