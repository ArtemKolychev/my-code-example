<?php

declare(strict_types=1);

namespace App\Application\DTO\Request;

final readonly class EditArticleRequest
{
    public function __construct(
        public string $title,
        public string $description,
        public ?float $price = null,
    ) {
    }
}
