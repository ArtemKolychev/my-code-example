<?php

declare(strict_types=1);

namespace App\Auth\Http\Request;

final readonly class RefreshVO
{
    public function __construct(
        public readonly string $refreshToken,
    ) {}
}
