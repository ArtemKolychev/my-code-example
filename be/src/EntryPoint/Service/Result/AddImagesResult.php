<?php

declare(strict_types=1);

namespace App\EntryPoint\Service\Result;

/** Result VO returned by AddImagesFormHandler. */
final readonly class AddImagesResult
{
    public function __construct(
        public bool $redirectNeeded,
        public ?int $batchId = null,
        /** @var array<string, string> Flash type → message pairs to add. */
        public array $flashes = [],
    ) {
    }
}
