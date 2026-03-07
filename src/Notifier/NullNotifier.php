<?php

declare(strict_types=1);

namespace H13\FeedPulse\Notifier;

use H13\FeedPulse\Contract\NotifierInterface;
use H13\FeedPulse\Reason\Entity\Draft;

final class NullNotifier implements NotifierInterface
{
    /** @param list<Draft> $drafts */
    public function notify(array $drafts): void
    {
    }
}
