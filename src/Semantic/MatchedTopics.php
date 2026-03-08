<?php

declare(strict_types=1);

namespace H13\FeedPulse\Semantic;

use Be\Framework\Attribute\Validate;
use DomainException;

use function count;

/**
 * List of matched topic names
 *
 * Validates that at least one topic is matched.
 *
 * @link alps/profile.xml#matchedTopics ALPS semantic descriptor
 */
final class MatchedTopics
{
    /** @param list<string> $matchedTopics */
    #[Validate]
    public function validate(array $matchedTopics): void
    {
        if (count($matchedTopics) === 0) {
            throw new DomainException('MatchedTopics must not be empty');
        }
    }
}
