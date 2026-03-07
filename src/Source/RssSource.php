<?php

declare(strict_types=1);

namespace H13\FeedPulse\Source;

use H13\FeedPulse\Contract\SourceInterface;
use H13\FeedPulse\Reason\Entity\FeedItem;
use Laminas\Feed\Reader\Reader;
use Symfony\Component\Yaml\Yaml;

final class RssSource implements SourceInterface
{
    private readonly string $configPath;

    public function __construct()
    {
        $this->configPath = dirname(__DIR__, 2) . '/config/sources.yaml';
    }

    /** @return list<FeedItem> */
    public function fetch(): array
    {
        $config = Yaml::parseFile($this->configPath);
        $sources = $config['sources'] ?? [];
        $items = [];

        foreach ($sources as $source) {
            $items = [...$items, ...$this->fetchFeed($source)];
        }

        return $items;
    }

    /**
     * @param array{name: string, url: string, category: string} $source
     * @return list<FeedItem>
     */
    private function fetchFeed(array $source): array
    {
        try {
            $feed = Reader::import($source['url']);
        } catch (\Throwable $e) {
            error_log("Failed to fetch {$source['name']}: {$e->getMessage()}");
            return [];
        }

        $items = [];
        foreach ($feed as $entry) {
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
