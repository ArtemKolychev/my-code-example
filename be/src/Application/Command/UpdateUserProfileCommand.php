<?php

declare(strict_types=1);

namespace App\Application\Command;

/** Synchronous command: update user profile fields. */
final readonly class UpdateUserProfileCommand
{
    public function __construct(
        public int $userId,
        public ?string $name,
        public ?string $address,
        public ?string $zip,
        public ?string $phone,
    ) {
    }
}
