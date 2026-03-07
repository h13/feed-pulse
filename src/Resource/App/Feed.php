<?php

declare(strict_types=1);

namespace H13\FeedPulse\Resource\App;

use BEAR\Resource\ResourceObject;
use BEAR\ToolUse\Attribute\Tool;
use H13\FeedPulse\Contract\MatcherInterface;
use H13\FeedPulse\Contract\SourceInterface;
use H13\FeedPulse\Reason\StateStore;
use Ray\Di\Di\Inject;

use function array_filter;
use function array_map;
use function array_values;
use function count;

#[Tool(description: 'Crawl feeds, match against interests, and return scored items')]
class Feed extends ResourceObject
{
    #[Inject]
    public function __construct(
        private readonly SourceInterface $source,
        private readonly MatcherInterface $matcher,
        private readonly StateStore $stateStore,
    ) {
    }

    /**
     * @param float $threshold Minimum score to include (default: 0.5)
     * @param bool  $newOnly   Only return items not previously processed (default: true)
     */
    public function onGet(float $threshold = 0.5, bool $newOnly = true): static
    {
        $items = $this->source->fetch();
        $matched = $this->matcher->match($items, $threshold);

        if ($newOnly) {
            $matched = array_values(array_filter(
                $matched,
                fn ($item) => ! $this->stateStore->isProcessed($item->feed->link),
            ));
        }

        $this->body = [
            'count' => count($matched),
            'items' => array_map(static fn ($item) => [
                'title' => $item->feed->title,
                'link' => $item->feed->link,
                'source' => $item->feed->source,
                'score' => $item->score,
                'matchedTopics' => $item->matchedTopics,
            ], $matched),
        ];

        return $this;
    }
}
