<?php

declare(strict_types=1);

namespace App\Application\DTO\Clicker;

final readonly class ActionSuggestPricePayload implements ClickerPayloadInterface
{
    public function __construct(
        public string $jobId,
        public ?int $articleId,
        public string $title,
        public string $description,
        public string $condition,
    ) {
    }

    public function toArray(): array
    {
        return [
            'jobId' => $this->jobId,
            'articleId' => $this->articleId,
            'title' => $this->title,
            'description' => $this->description,
            'condition' => $this->condition,
        ];
    }
}
