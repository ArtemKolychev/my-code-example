<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

final readonly class PublishArticlesResult
{
    public function __construct(
        public int $published,
        public int $needsInput,
        public bool $profileIncomplete = false,
    ) {
    }
}
