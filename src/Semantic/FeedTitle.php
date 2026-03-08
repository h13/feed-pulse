<?php

declare(strict_types=1);

namespace H13\FeedPulse\Semantic;

use Be\Framework\Attribute\Validate;
use DomainException;

use function trim;

/**
 * Title of the feed item
 *
 * Validates that feed title is non-empty.
 *
 * @link alps/profile.xml#feedTitle ALPS semantic descriptor
 */
final class FeedTitle
{
    #[Validate]
    public function validate(string $feedTitle): void
    {
        if (trim($feedTitle) === '') {
            throw new DomainException('Feed title must not be empty');
        }
    }
}
