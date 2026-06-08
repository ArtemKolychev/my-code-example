<?php

declare(strict_types=1);

namespace App\Auth\Application\DTO\Response;

final readonly class TokenPairDto
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly int $expiresIn,
    ) {}
}
