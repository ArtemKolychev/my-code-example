<?php

declare(strict_types=1);

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
readonly class EnrichVehicleMessage
{
    public function __construct(
        private int $articleId,
        private ?string $vin = null,
        private ?string $spz = null, // Czech/Slovak license plate (SPZ)
    ) {
    }

    public function getArticleId(): int
    {
        return $this->articleId;
    }

    public function getVin(): ?string
    {
        return $this->vin;
    }

    public function getSpz(): ?string
    {
        return $this->spz;
    }
}
