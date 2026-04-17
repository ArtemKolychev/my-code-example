<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

final readonly class WithdrawArticleResult
{
    public function __construct(
        public bool $success,
        public ?string $error = null,
    ) {
    }
}
