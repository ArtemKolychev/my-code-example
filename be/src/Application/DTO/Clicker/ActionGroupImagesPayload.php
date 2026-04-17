<?php

declare(strict_types=1);

namespace App\Application\DTO\Clicker;

use App\Domain\Enum\Condition;

final readonly class ActionGroupImagesPayload implements ClickerPayloadInterface
{
    /** @param list<ImageData> $articleImages */
    public function __construct(
        public string $jobId,
        public ?int $batchId,
        public array $articleImages,
        public ?string $vehicleIdentifier,
        public ?Condition $condition,
    ) {
    }

    public function toArray(): array
    {
        return [
            'jobId' => $this->jobId,
            'batchId' => $this->batchId,
            'articleImages' => array_map(static fn (ImageData $img): array => $img->toArray(), $this->articleImages),
            'vehicleIdentifier' => $this->vehicleIdentifier,
            'condition' => $this->condition?->value,
        ];
    }
}
