<?php

declare(strict_types=1);

namespace H13\FeedPulse\Reason\Entity;

final readonly class ScoredItem
{
    /**
     * @param list<string> $matchedTopics
     */
    public function __construct(
        public FeedItem $feed,
        public float $score,
        public array $matchedTopics,
    ) {
    }
}
