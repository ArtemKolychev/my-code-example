<?php

declare(strict_types=1);

namespace App\Application\Command;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
final readonly class SuggestPriceCommand
{
    public function __construct(
        private int $articleId,
    ) {
    }

    public function getArticleId(): int
    {
        return $this->articleId;
    }
}
