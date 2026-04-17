<?php

declare(strict_types=1);

namespace App\Application\Command;

/** Synchronous command: generate a password reset token and send the reset email. */
final readonly class SendPasswordResetEmailCommand
{
    public function __construct(
        public string $email,
    ) {
    }
}
