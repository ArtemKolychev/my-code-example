<?php

declare(strict_types=1);

namespace App\EntryPoint\Service\Result;

final readonly class PostArticlesResult
{
    /**
     * @param array<string, string> $flashes
     * @param array<string, mixed>  $redirectParams
     */
    public function __construct(
        public string $redirectRoute,
        public array $redirectParams = [],
        public array $flashes = [],
    ) {
    }
}
