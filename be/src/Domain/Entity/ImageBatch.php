<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Enum\Condition;
use App\Domain\ValueObject\BatchStatus;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'image_batch')]
class ImageBatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    public string $jobId;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $user;

    /** @var string[] */
    #[ORM\Column(type: 'json')]
    public array $imagePaths = [];

    #[ORM\Column(length: 32, enumType: BatchStatus::class, options: ['default' => 'pending'])]
    public BatchStatus $status = BatchStatus::Pending;

    /** @var int[]|null */
    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $articleIds = null;

    #[ORM\Column(length: 32, enumType: Condition::class, nullable: true)]
    public ?Condition $condition = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $pendingInput = null;

    #[ORM\Column(type: 'datetime_immutable')]
    public DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function setJobId(string $jobId): static
    {
        $this->jobId = $jobId;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /** @return string[] */
    public function getImagePaths(): array
    {
        return $this->imagePaths;
    }

    /** @param string[] $imagePaths */
    public function setImagePaths(array $imagePaths): static
    {
        $this->imagePaths = $imagePaths;

        return $this;
    }

    public function getStatus(): BatchStatus
    {
        return $this->status;
    }

    public function setStatus(BatchStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function startProcessing(string $jobId): void
    {
        $this->jobId = $jobId;
        $this->status = BatchStatus::Processing;
    }

    public function fail(): void
    {
        $this->status = BatchStatus::Failed;
    }

    /** @param int[] $articleIds */
    public function complete(array $articleIds): void
    {
        $this->articleIds = $articleIds;
        $this->pendingInput = null;
        $this->status = BatchStatus::Completed;
    }

    /** @param array<string, mixed> $pendingInput */
    public function requestInput(array $pendingInput): void
    {
        $this->pendingInput = $pendingInput;
        $this->status = BatchStatus::NeedsInput;
    }

    /** @return int[]|null */
    public function getArticleIds(): ?array
    {
        return $this->articleIds;
    }

    /** @param int[]|null $articleIds */
    public function setArticleIds(?array $articleIds): static
    {
        $this->articleIds = $articleIds;

        return $this;
    }
}
