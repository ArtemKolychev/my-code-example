<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ImageBatchRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImageBatchRepository::class)]
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

    #[ORM\Column(length: 32, options: ['default' => 'pending'])]
    public string $status = 'pending';

    /** @var int[]|null */
    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $articleIds = null;

    #[ORM\Column(length: 32, nullable: true)]
    public ?string $condition = null;

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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
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
