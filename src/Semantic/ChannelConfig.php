<?php

declare(strict_types=1);

namespace H13\FeedPulse\Semantic;

use Be\Framework\Attribute\Validate;
use DomainException;

use function count;

/**
 * Channel configuration map
 *
 * Validates that channel config is non-empty.
 *
 * @link alps/profile.xml#channelConfig ALPS semantic descriptor
 */
final class ChannelConfig
{
    /** @param array<string, mixed> $channelConfig */
    #[Validate]
    public function validate(array $channelConfig): void
    {
        if (count($channelConfig) === 0) {
            throw new DomainException('Channel config must not be empty');
        }
    }
}
