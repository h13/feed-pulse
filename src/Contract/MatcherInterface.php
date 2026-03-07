<?php

declare(strict_types=1);

namespace H13\FeedPulse\Contract;

use H13\FeedPulse\Reason\Entity\FeedItem;
use H13\FeedPulse\Reason\Entity\ScoredItem;

interface MatcherInterface
{
    /**
     * @param list<FeedItem> $items
     *
     * @return list<ScoredItem>
     */
    public function match(array $items, float $threshold = 0.5): array;
}
