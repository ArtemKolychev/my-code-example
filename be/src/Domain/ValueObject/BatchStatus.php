<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * Lifecycle status of an AI image-processing batch (ImageBatch).
 *
 * Transitions:
 *   Pending → Processing → Completed | Failed | NeedsInput
 *   NeedsInput → Processing → Completed | Failed
 */
enum BatchStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case NeedsInput = 'needs_input';
    case Failed = 'failed';
    case Completed = 'completed';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Failed => true,
            default => false,
        };
    }

    public function isAwaitingUserAction(): bool
    {
        return self::NeedsInput === $this;
    }
}
