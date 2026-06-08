<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\Login;

final readonly class LoginCommand
{
    public function __construct(
        public readonly string $jobId,
        public readonly string $email,
        public readonly string $password,
    ) {}
}
