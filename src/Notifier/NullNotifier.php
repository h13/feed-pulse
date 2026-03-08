<?php

declare(strict_types=1);

namespace H13\FeedPulse\Notifier;

use H13\FeedPulse\Contract\NotifierInterface;
use H13\FeedPulse\Reason\Entity\Draft;
use Override;

final class NullNotifier implements NotifierInterface
{
    /** @param list<Draft> $drafts */
    #[Override]
    public function notify(array $drafts): void
    {
    }
}
