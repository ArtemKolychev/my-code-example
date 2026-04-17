<?php

declare(strict_types=1);

namespace App\Application\DTO\Clicker;

final readonly class ActionInputPayload implements ClickerPayloadInterface
{
    public function __construct(
        public string $jobId,
        public string $code,
    ) {
    }

    public function toArray(): array
    {
        return ['jobId' => $this->jobId, 'code' => $this->code];
    }
}
