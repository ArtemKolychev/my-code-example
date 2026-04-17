<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

final class MissingCredentialException extends RuntimeException
{
    public static function forUserAndService(int $userId, string $service): self
    {
        return new self("No {$service} credentials for user id:[{$userId}]");
    }
}
