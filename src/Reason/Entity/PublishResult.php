<?php

declare(strict_types=1);

namespace H13\FeedPulse\Reason\Entity;

final readonly class PublishResult
{
    public function __construct(
        public string $channel,
        public string $title,
        public string|null $url,
        public string|null $error,
        public string $publishedAt,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->error === null;
    }
}
