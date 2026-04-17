<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Enum\Platform;
use App\Domain\ValueObject\SubmissionStatus;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'article_submission')]
#[ORM\UniqueConstraint(columns: ['article_id', 'platform'])]
#[ORM\Index(columns: ['job_id'], name: 'idx_article_submission_job_id')]
#[ORM\HasLifecycleCallbacks]
class ArticleSubmission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Article $article = null;

    #[ORM\Column(type: 'string', enumType: Platform::class, length: 20)]
    private Platform $platform;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $jobId = null;

    #[ORM\Column(type: 'string', enumType: SubmissionStatus::class, length: 32, options: ['default' => 'pending'])]
    private SubmissionStatus $status = SubmissionStatus::Pending;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $resultData = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $errorData = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $pendingInput = null;

    /** @var array{step: string, stepIndex: int, totalSteps: int, message: string}|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $progressData = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): static
    {
        $this->article = $article;

        return $this;
    }

    public function getPlatform(): Platform
    {
        return $this->platform;
    }

    public function setPlatform(Platform $platform): static
    {
        $this->platform = $platform;

        return $this;
    }

    public function getJobId(): ?string
    {
        return $this->jobId;
    }

    public function setJobId(?string $jobId): static
    {
        $this->jobId = $jobId;

        return $this;
    }

    public function getStatus(): SubmissionStatus
    {
        return $this->status;
    }

    public function setStatus(SubmissionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getResultData(): ?array
    {
        return $this->resultData;
    }

    /** @param array<string, mixed>|null $resultData */
    public function setResultData(?array $resultData): static
    {
        $this->resultData = $resultData;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getErrorData(): ?array
    {
        return $this->errorData;
    }

    /** @param array<string, mixed>|null $errorData */
    public function setErrorData(?array $errorData): static
    {
        $this->errorData = $errorData;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getPendingInput(): ?array
    {
        return $this->pendingInput;
    }

    /** @param array<string, mixed>|null $pendingInput */
    public function setPendingInput(?array $pendingInput): static
    {
        $this->pendingInput = $pendingInput;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return array{step: string, stepIndex: int, totalSteps: int, message: string}|null */
    public function getProgressData(): ?array
    {
        return $this->progressData;
    }

    /** @param array{step: string, stepIndex: int, totalSteps: int, message: string}|null $progressData */
    public function setProgressData(?array $progressData): static
    {
        $this->progressData = $progressData;

        return $this;
    }
}
