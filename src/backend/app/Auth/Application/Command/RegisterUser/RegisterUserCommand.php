<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\RegisterUser;

final readonly class RegisterUserCommand
{
    public function __construct(
        public readonly string $jobId,
        public readonly string $email,
        public readonly string $password,
        public readonly string $name,
    ) {}
}
