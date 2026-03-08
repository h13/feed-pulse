<?php

declare(strict_types=1);

namespace H13\FeedPulse\Semantic;

use Be\Framework\Attribute\Validate;
use DomainException;

use function trim;

/**
 * Generated content text
 *
 * Validates that content is non-empty.
 *
 * @link alps/profile.xml#content ALPS semantic descriptor
 */
final class Content
{
    #[Validate]
    public function validate(string $content): void
    {
        if (trim($content) === '') {
            throw new DomainException('Content must not be empty');
        }
    }
}
