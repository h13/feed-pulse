<?php

declare(strict_types=1);

namespace H13\FeedPulse\Semantic;

use Be\Framework\Attribute\Validate;
use DomainException;

use function trim;

/**
 * ISO 8601 creation timestamp
 *
 * Validates that timestamp is non-empty.
 *
 * @link alps/profile.xml#createdAt ALPS semantic descriptor
 */
final class CreatedAt
{
    #[Validate]
    public function validate(string $createdAt): void
    {
        if (trim($createdAt) === '') {
            throw new DomainException('CreatedAt timestamp must not be empty');
        }
    }
}
