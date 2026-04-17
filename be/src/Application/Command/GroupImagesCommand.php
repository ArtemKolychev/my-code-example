<?php

declare(strict_types=1);

namespace App\Application\Command;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
final readonly class GroupImagesCommand
{
    public function __construct(
        private int $batchId,
        private ?string $vehicleIdentifier = null,
        private ?string $condition = null,
    ) {
    }

    public function getBatchId(): int
    {
        return $this->batchId;
    }

    public function getVehicleIdentifier(): ?string
    {
        return $this->vehicleIdentifier;
    }

    public function getCondition(): ?string
    {
        return $this->condition;
    }
}
