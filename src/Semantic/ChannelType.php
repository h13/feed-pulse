<?php

declare(strict_types=1);

namespace H13\FeedPulse\Semantic;

use Be\Framework\Attribute\Validate;
use DomainException;

use function trim;

/**
 * Channel type identifier (e.g. "x", "wordpress")
 *
 * Validates that channel type is non-empty.
 *
 * @link alps/profile.xml#channelType ALPS semantic descriptor
 */
final class ChannelType
{
    #[Validate]
    public function validate(string $channelType): void
    {
        if (trim($channelType) === '') {
            throw new DomainException('Channel type must not be empty');
        }
    }
}
