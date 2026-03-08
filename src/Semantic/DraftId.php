<?php

declare(strict_types=1);

namespace H13\FeedPulse\Semantic;

use Be\Framework\Attribute\Validate;
use DomainException;

use function trim;

/**
 * Unique identifier for a draft
 *
 * Validates that draft ID is non-empty.
 *
 * @link alps/profile.xml#draftId ALPS semantic descriptor
 */
final class DraftId
{
    #[Validate]
    public function validate(string $draftId): void
    {
        if (trim($draftId) === '') {
            throw new DomainException('Draft ID must not be empty');
        }
    }
}
