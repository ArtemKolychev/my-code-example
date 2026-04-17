<?php

declare(strict_types=1);

namespace App\Application\Command;

/** Synchronous command: delete an image file and remove it from the database. */
final readonly class DeleteImageCommand
{
    public function __construct(
        public int $imageId,
    ) {
    }
}
