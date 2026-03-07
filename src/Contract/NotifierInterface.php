<?php

declare(strict_types=1);

namespace H13\FeedPulse\Contract;

use H13\FeedPulse\Reason\Entity\Draft;

interface NotifierInterface
{
    /** @param list<Draft> $drafts */
    public function notify(array $drafts): void;
}
