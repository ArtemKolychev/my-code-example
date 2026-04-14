<?php

declare(strict_types=1);

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
readonly class SuggestPriceMessage
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
