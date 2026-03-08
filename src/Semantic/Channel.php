<?php

declare(strict_types=1);

namespace H13\FeedPulse\Semantic;

use Be\Framework\Attribute\Validate;
use DomainException;

use function trim;

/**
 * Target channel name
 *
 * Validates that channel name is non-empty.
 *
 * @link alps/profile.xml#channel ALPS semantic descriptor
 */
final class Channel
{
    #[Validate]
    public function validate(string $channel): void
    {
        if (trim($channel) === '') {
            throw new DomainException('Channel name must not be empty');
        }
    }
}
