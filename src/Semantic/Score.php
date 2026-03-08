<?php

declare(strict_types=1);

namespace H13\FeedPulse\Semantic;

use Be\Framework\Attribute\Validate;
use DomainException;

/**
 * Interest match score
 *
 * Validates that score is non-negative.
 *
 * @link alps/profile.xml#score ALPS semantic descriptor
 */
final class Score
{
    #[Validate]
    public function validate(float $score): void
    {
        if ($score < 0.0) {
            throw new DomainException("Score must be non-negative, got {$score}");
        }
    }
}
