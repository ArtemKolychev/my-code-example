<?php

declare(strict_types=1);

namespace App\Auth\Http\Request;

final readonly class RegisterVO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
    ) {}
}
