<?php

declare(strict_types=1);

namespace App\Domain\Event;

readonly class ClickerEvent
{
    /**
     * @param array<string, mixed>        $result
     * @param string[]|null               $imageUrls
     * @param array<string, mixed>[]|null $fields
     */
    public function __construct(
        private string $type,
        private string $jobId,
        private ?string $step = null,
        private ?string $error = null,
        private ?string $inputType = null,
        private ?string $inputPrompt = null,
        private array $result = [],
        private ?int $articleId = null,
        private ?array $imageUrls = null,
        private ?array $fields = null,
        private ?int $stepIndex = null,
        private ?int $totalSteps = null,
        private ?string $message = null,
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getStep(): ?string
    {
        return $this->step;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getInputType(): ?string
    {
        return $this->inputType;
    }

    public function getInputPrompt(): ?string
    {
        return $this->inputPrompt;
    }

    /** @return array<string, mixed> */
    public function getResult(): array
    {
        return $this->result;
    }

    public function getArticleId(): ?int
    {
        return $this->articleId;
    }

    /** @return string[]|null */
    public function getImageUrls(): ?array
    {
        return $this->imageUrls;
    }

    /** @return array<string, mixed>[]|null */
    public function getFields(): ?array
    {
        return $this->fields;
    }

    public function getStepIndex(): ?int
    {
        return $this->stepIndex;
    }

    public function getTotalSteps(): ?int
    {
        return $this->totalSteps;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }
}
