<?php

declare(strict_types=1);

namespace H13\FeedPulse\Publisher;

use H13\FeedPulse\Contract\PublisherInterface;
use H13\FeedPulse\Reason\Entity\Draft;
use H13\FeedPulse\Reason\Entity\PublishResult;

use function date;

final class PublisherPool
{
    /** @param array<string, PublisherInterface> $publishers channel name → publisher */
    public function __construct(
        private readonly array $publishers,
    ) {
    }

    public function publish(Draft $draft): PublishResult
    {
        $publisher = $this->publishers[$draft->channel] ?? null;

        if ($publisher === null) {
            return new PublishResult(
                channel: $draft->channel,
                title: $draft->item->feed->title,
                url: null,
                error: "No publisher configured for channel '{$draft->channel}'",
                publishedAt: date('c'),
            );
        }

        return $publisher->publish($draft);
    }
}
