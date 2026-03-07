<?php

declare(strict_types=1);

namespace H13\FeedPulse\Contract;

use H13\FeedPulse\Reason\Entity\FeedItem;

interface SourceInterface
{
    /** @return list<FeedItem> */
    public function fetch(): array;
}
