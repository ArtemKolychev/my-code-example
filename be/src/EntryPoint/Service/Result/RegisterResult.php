<?php

declare(strict_types=1);

namespace App\EntryPoint\Service\Result;

final readonly class RegisterResult
{
    public function __construct(
        public bool $dispatched,
    ) {
    }
}
