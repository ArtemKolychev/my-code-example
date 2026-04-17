<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * Lifecycle status of a platform submission for a single Article.
 *
 * Transitions:
 *   Pending → Processing → Completed | Failed | Withdrawn
 *   Pending → Deleting → Completed | Failed
 */
enum SubmissionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Withdrawn = 'withdrawn';
    case Deleting = 'deleting';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Failed, self::Withdrawn => true,
            default => false,
        };
    }

    public function canRetry(): bool
    {
        return self::Failed === $this || self::Withdrawn === $this;
    }
}
