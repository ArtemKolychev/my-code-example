<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

final class InsufficientTokensException extends RuntimeException
{
    public static function forUser(int $userId): self
    {
        return new self(sprintf('User %d has no AI tokens remaining.', $userId));
    }
}
