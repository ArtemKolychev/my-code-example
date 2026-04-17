<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\Entity\User;

/** Synchronous command: persist (create or update) a service credential for a user. */
final readonly class SaveServiceCredentialCommand
{
    public function __construct(
        public User $user,
        public string $service,
        public ?string $login,
        public ?string $password,
        public bool $showLogin,
        public string $appSecret,
    ) {
    }
}
