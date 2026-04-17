<?php

declare(strict_types=1);

namespace App\EntryPoint\Service\Result;

final readonly class ForgotPasswordResult
{
    public function __construct(
        public bool $dispatched,
    ) {
    }
}
