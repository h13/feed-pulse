<?php

declare(strict_types=1);

namespace H13\FeedPulse\Notifier;

use H13\FeedPulse\Contract\NotifierInterface;

final class NullNotifier implements NotifierInterface
{
    public function notify(array $drafts): void
    {
    }
}
