<?php

declare(strict_types=1);

namespace App\Application\DTO\Clicker;

final readonly class ActionDeletePayload implements ClickerPayloadInterface
{
    public function __construct(
        public string $jobId,
        public ?int $articleId,
        public int $userId,
        public string $platform,
        public string $articleUrl,
        public CredentialData $credential,
    ) {
    }

    public function toArray(): array
    {
        return [
            'jobId' => $this->jobId,
            'articleId' => $this->articleId,
            'userId' => $this->userId,
            'platform' => $this->platform,
            'articleUrl' => $this->articleUrl,
            'credential' => $this->credential->toArray(),
        ];
    }
}
