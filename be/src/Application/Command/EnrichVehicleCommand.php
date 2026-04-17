<?php

declare(strict_types=1);

namespace App\Application\Command;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
final readonly class EnrichVehicleCommand
{
    public function __construct(
        private int $articleId,
        private ?string $vin = null,
        private ?string $spz = null,
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
