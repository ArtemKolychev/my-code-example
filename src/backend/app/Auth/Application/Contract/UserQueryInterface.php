<?php

declare(strict_types=1);

namespace App\Auth\Application\Contract;

use App\Auth\Domain\User\User;
use App\Auth\Domain\User\ValueObject\Email;

interface UserQueryInterface
{
    public function findByEmail(Email $email): ?User;

    public function existsByEmail(Email $email): bool;
}
