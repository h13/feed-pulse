<?php

declare(strict_types=1);

namespace H13\FeedPulse\Resource\App;

use BEAR\Resource\ResourceObject;
use BEAR\ToolUse\Attribute\Tool;
use H13\FeedPulse\Reason\Crawler;
use H13\FeedPulse\Reason\Matcher;
use H13\FeedPulse\Reason\StateStore;
use Ray\Di\Di\Inject;

#[Tool(description: 'Crawl RSS feeds, match against interests, and return scored items')]
class Feed extends ResourceObject
{
    private Crawler $crawler;
    private Matcher $matcher;
    private StateStore $stateStore;

    #[Inject]
    public function __construct(Crawler $crawler, Matcher $matcher, StateStore $stateStore)
    {
        $this->crawler = $crawler;
        $this->matcher = $matcher;
        $this->stateStore = $stateStore;
    }

    /**
     * Crawl configured RSS feeds and return interest-matched items.
     *
     * @param float $threshold Minimum score to include (default: 0.5)
     * @param bool  $newOnly   Only return items not previously processed (default: true)
     */
    public function onGet(float $threshold = 0.5, bool $newOnly = true): static
    {
        $items = $this->crawler->crawl();
        $matched = $this->matcher->match($items, $threshold);

        if ($newOnly) {
            $matched = array_values(array_filter(
                $matched,
                fn ($item) => ! $this->stateStore->isProcessed($item->feed->link),
            ));
        }

        $this->body = [
            'count' => count($matched),
            'items' => array_map(fn ($item) => [
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
