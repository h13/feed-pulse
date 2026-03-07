<?php

declare(strict_types=1);

namespace H13\FeedPulse\Contract;

use H13\FeedPulse\Reason\Entity\Draft;
use H13\FeedPulse\Reason\Entity\PublishResult;

interface PublisherInterface
{
    public function publish(Draft $draft): PublishResult;
}
