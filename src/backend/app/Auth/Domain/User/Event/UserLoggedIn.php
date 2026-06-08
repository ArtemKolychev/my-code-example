<?php

declare(strict_types=1);

namespace App\Auth\Domain\User\Event;

final readonly class UserLoggedIn
{
    public function __construct(
        public string $jobId,
        public string $userId,
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
        public string $occurredAt,
    ) {}
}
