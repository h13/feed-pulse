<?php

declare(strict_types=1);

namespace H13\FeedPulse\Semantic;

use Be\Framework\Attribute\Validate;
use DomainException;
use H13\FeedPulse\Reason\Entity\FeedItem;

/**
 * Feed item entity
 *
 * Validates that feed item is a valid FeedItem instance.
 *
 * @link alps/profile.xml#feed ALPS semantic descriptor
 */
final class Feed
{
    #[Validate]
    public function validate(FeedItem $feed): void
    {
        if ($feed->link === '') {
            throw new DomainException('Feed must have a link');
        }
    }
}
