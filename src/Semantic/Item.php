<?php

declare(strict_types=1);

namespace H13\FeedPulse\Semantic;

use Be\Framework\Attribute\Validate;
use DomainException;
use H13\FeedPulse\Reason\Entity\ScoredItem;

/**
 * Scored feed item entity
 *
 * Validates that scored item has a valid feed link.
 *
 * @link alps/profile.xml#item ALPS semantic descriptor
 */
final class Item
{
    #[Validate]
    public function validate(ScoredItem $item): void
    {
        if ($item->feed->link === '') {
            throw new DomainException('Item must have a valid feed link');
        }
    }
}
