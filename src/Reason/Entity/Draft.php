<?php

declare(strict_types=1);

namespace H13\FeedPulse\Reason\Entity;

final readonly class Draft
{
    public function __construct(
        public string $id,
        public string $channel,
        public string $content,
        public ScoredItem $item,
        public string $createdAt,
    ) {
    }
}
