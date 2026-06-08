<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\Refresh;

final readonly class RefreshCommand
{
    public function __construct(
        public readonly string $refreshToken,
    ) {}
}
