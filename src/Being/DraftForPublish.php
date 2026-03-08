<?php

declare(strict_types=1);

namespace H13\FeedPulse\Being;

use Be\Framework\Attribute\Be;
use H13\FeedPulse\Reason\Entity\Draft;
use H13\FeedPulse\Reason\Entity\ScoredItem;

/**
 * Entry point for the publish Be chain
 *
 * Wraps a stored Draft entity for metamorphosis into BePublish.
 *
 * @link alps/profile.xml#Draft ALPS state
 */
#[Be(BePublish::class)]
final readonly class DraftForPublish
{
    public string $draftId;
    public string $channel;
    public string $content;
    public ScoredItem $item;
    public string $createdAt;

    public function __construct(Draft $draft)
    {
        $this->draftId = $draft->id;
        $this->channel = $draft->channel;
        $this->content = $draft->content;
        $this->item = $draft->item;
        $this->createdAt = $draft->createdAt;
    }
}
