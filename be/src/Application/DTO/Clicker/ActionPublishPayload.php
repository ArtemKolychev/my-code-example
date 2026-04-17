<?php

declare(strict_types=1);

namespace App\Application\DTO\Clicker;

final readonly class ActionPublishPayload implements ClickerPayloadInterface
{
    public function __construct(
        public string $jobId,
        public ?int $articleId,
        public ?int $userId,
        public string $platform,
        public ArticleData $article,
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
            'article' => $this->article->toArray(),
            'credential' => $this->credential->toArray(),
        ];
    }
}
