<?php

declare(strict_types=1);

namespace App\Application\DTO\Clicker;

final readonly class ActionEnrichVehiclePayload implements ClickerPayloadInterface
{
    /** @param list<ImageData> $articleImages */
    public function __construct(
        public string $jobId,
        public ?int $articleId,
        public ?string $vin,
        public ?string $spz,
        public array $articleImages,
    ) {
    }

    public function toArray(): array
    {
        return [
            'jobId' => $this->jobId,
            'articleId' => $this->articleId,
            'vin' => $this->vin,
            'spz' => $this->spz,
            'articleImages' => array_map(static fn (ImageData $img): array => $img->toArray(), $this->articleImages),
        ];
    }
}
