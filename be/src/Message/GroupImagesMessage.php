<?php

declare(strict_types=1);

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
readonly class GroupImagesMessage
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
