<?php

declare(strict_types=1);

namespace App\Auth\Domain\User\Event;

final readonly class UserRegistered
{
    public function __construct(
        public readonly string $jobId,
        public readonly string $userId,
        public readonly string $email,
        public readonly string $occurredAt,
    ) {}
}
