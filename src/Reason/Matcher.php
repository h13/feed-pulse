<?php

declare(strict_types=1);

namespace H13\FeedPulse\Reason;

use H13\FeedPulse\Contract\MatcherInterface;
use H13\FeedPulse\Reason\Entity\FeedItem;
use H13\FeedPulse\Reason\Entity\ScoredItem;
use Ray\Di\Di\Named;
use Symfony\Component\Yaml\Yaml;

use function array_filter;
use function array_map;
use function is_array;
use function str_contains;
use function strtolower;
use function usort;

final class Matcher implements MatcherInterface
{
    private readonly string $configPath;

    public function __construct(
        #[Named('app_dir')]
        string $appDir,
    ) {
        $this->configPath = $appDir . '/config/interests.yaml';
    }

    /**
     * @param list<FeedItem> $items
     *
     * @return list<ScoredItem>
     */
    public function match(array $items, float $threshold = 0.5): array
    {
        $interests = $this->loadInterests();

        $scored = array_map(
            fn (FeedItem $item) => $this->scoreItem($item, $interests),
            $items,
        );

        $filtered = array_filter(
            $scored,
            static fn (ScoredItem $item) => $item->score >= $threshold,
        );

        usort($filtered, static fn (ScoredItem $a, ScoredItem $b) => $b->score <=> $a->score);

        return $filtered;
    }

    /** @param list<array{topic: string, keywords: list<string>, weight: float}> $interests */
    private function scoreItem(FeedItem $item, array $interests): ScoredItem
    {
        $text = strtolower("{$item->title} {$item->description}");
        $score = 0.0;
        $matchedTopics = [];

        foreach ($interests as $interest) {
            foreach ($interest['keywords'] as $keyword) {
                if (str_contains($text, strtolower($keyword))) {
                    $score += $interest['weight'];
                    $matchedTopics[] = $interest['topic'];
                    break;
                }
            }
        }

        return new ScoredItem(
            feed: $item,
            score: $score,
            matchedTopics: $matchedTopics,
        );
    }

    /** @return list<array{topic: string, keywords: list<string>, weight: float}> */
    private function loadInterests(): array
    {
        $config = Yaml::parseFile($this->configPath);
        assert(is_array($config));

        /** @var list<array{topic: string, keywords: list<string>, weight: float}> */
        return $config['interests'] ?? [];
    }
}
