<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\Platform;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
readonly class PublishMessage
{
    public function __construct(
        private int $articleId,
        private int $userId,
        private Platform $platform = Platform::Seznam,
    ) {
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getArticleId(): int
    {
        return $this->articleId;
    }

    public function getPlatform(): Platform
    {
        return $this->platform;
    }
}
