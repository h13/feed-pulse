<?php

declare(strict_types=1);

namespace H13\FeedPulse\Reason\Entity;

final readonly class FeedItem
{
    public function __construct(
        public string $title,
        public string $link,
        public string $description,
        public string $pubDate,
        public string $source,
        public string $category,
    ) {
    }
}
