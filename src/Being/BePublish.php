<?php

declare(strict_types=1);

namespace H13\FeedPulse\Being;

use H13\FeedPulse\Publisher\PublisherPool;
use H13\FeedPulse\Reason\Entity\Draft;
use H13\FeedPulse\Reason\Entity\PublishResult;
use H13\FeedPulse\Reason\Entity\ScoredItem;
use Ray\Di\Di\Inject;
use Ray\InputQuery\Attribute\Input;

/**
 * Publish being — the state of a published draft
 *
 * Receives draft data via #[Input] and publishes through PublisherPool
 * in the constructor (metamorphosis).
 *
 * Terminal state for the publish chain.
 *
 * @link alps/profile.xml#PublishResult ALPS state
 */
final readonly class BePublish
{
    public PublishResult $result;

    public function __construct(
        #[Input] public string $draftId,
        #[Input] public string $channel,
        #[Input] public string $content,
        #[Input] public ScoredItem $item,
        #[Input] public string $createdAt,
        #[Inject] PublisherPool $publisherPool,
    ) {
        $draft = new Draft(
            id: $draftId,
            channel: $channel,
            content: $content,
            item: $item,
            createdAt: $createdAt,
        );
        $this->result = $publisherPool->publish($draft);
    }
}
