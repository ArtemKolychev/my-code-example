<?php

declare(strict_types=1);

namespace App\Application\Command;

/** Synchronous command: remove an article from the database. */
final readonly class RemoveArticleCommand
{
    public function __construct(
        public int $articleId,
    ) {
    }
}
