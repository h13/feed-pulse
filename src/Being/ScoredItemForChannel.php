<?php

declare(strict_types=1);

namespace H13\FeedPulse\Being;

use Be\Framework\Attribute\Be;
use H13\FeedPulse\Reason\Entity\FeedItem;
use H13\FeedPulse\Reason\Entity\ScoredItem;

/**
 * Entry point for the generation Be chain
 *
 * Wraps a ScoredItem with channel context for metamorphosis into BeDraft.
 *
 * @link alps/profile.xml#MatchedFeedCollection ALPS state
 */
#[Be(BeDraft::class)]
final readonly class ScoredItemForChannel
{
    public FeedItem $feed;

    public float $score;

    /** @var list<string> */
    public array $matchedTopics;

    /**
     * @param array<string, mixed> $channelConfig
     * @param list<string>         $matchedTopics
     */
    public function __construct(
        ScoredItem $item,
        public string $channel,
        public string $channelType,
        public array $channelConfig,
    ) {
        $this->feed = $item->feed;
        $this->score = $item->score;
        $this->matchedTopics = $item->matchedTopics;
    }
}
