<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

final class UserNotFoundException extends RuntimeException
{
    public static function forId(int $id): self
    {
        return new self("User id:[{$id}] not found");
    }
}
