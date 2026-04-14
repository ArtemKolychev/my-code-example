<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Category;
use App\Enum\Condition;
use App\Repository\ArticleRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\Table(name: 'article')]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 255)]
    public ?string $title = null;

    #[ORM\Column(type: 'text', nullable: false)]
    public ?string $description = null;

    #[ORM\Column(type: 'float', nullable: true)]
    public ?float $price = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $priceReasoning = null;

    #[ORM\Column(type: 'json', nullable: true)]
    /** @var array<int, array{name: string, url: string}>|null */
    public ?array $priceSources = null;

    #[ORM\OneToMany(targetEntity: Image::class, mappedBy: 'article', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    /** @var Collection<int, Image> */
    public Collection $images;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'articles')]
    public ?User $user = null;

    #[ORM\Column(type: 'json', nullable: true)]
    /** @var array<string, mixed>|object|string|null */
    public string|array|object|null $publicResultData = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?DateTimeImmutable $withdrawnAt = null;

    #[ORM\Column(type: 'json', nullable: true)]
    /** @var array<string, mixed>|null */
    private ?array $pendingInput = null;

    #[ORM\Column(type: 'string', enumType: Category::class, nullable: true)]
    public ?Category $category = null;

    #[ORM\Column(type: 'string', enumType: Condition::class, nullable: true)]
    public ?Condition $condition = null;

    #[ORM\Column(type: 'json', nullable: true)]
    /** @var array<string, mixed>|null */
    public ?array $meta = null;

    public function __construct()
    {
        $this->images = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, Image>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    /** @param Collection<int, Image> $images */
    public function setImages(Collection $images): static
    {
        $this->images = $images;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getPriceReasoning(): ?string
    {
        return $this->priceReasoning;
    }

    public function setPriceReasoning(?string $priceReasoning): static
    {
        $this->priceReasoning = $priceReasoning;

        return $this;
    }

    /** @return array<int, array{name: string, url: string}>|null */
    public function getPriceSources(): ?array
    {
        return $this->priceSources;
    }

    /** @param array<int, array{name: string, url: string}>|null $priceSources */
    public function setPriceSources(?array $priceSources): static
    {
        $this->priceSources = $priceSources;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getPublicResultData(): ?array
    {
        $data = $this->publicResultData;
        // Handle legacy double-encoded data (string instead of decoded object)
        if (is_string($data)) {
            /** @var array<string, mixed>|null */
            $data = json_decode($data, true);
        }
        if (is_object($data)) {
            $data = (array) $data;
        }

        return is_array($data) ? $data : null;
    }

    /** @param array<string, mixed>|string|object|null $publicResultData */
    public function setPublicResultData(string|array|object|null $publicResultData): self
    {
        // Doctrine handles JSON serialization for json column type — store raw value
        $this->publicResultData = is_string($publicResultData)
            ? json_decode($publicResultData, true)
            : $publicResultData;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getWithdrawnAt(): ?DateTimeImmutable
    {
        return $this->withdrawnAt;
    }

    public function setWithdrawnAt(?DateTimeImmutable $withdrawnAt): static
    {
        $this->withdrawnAt = $withdrawnAt;

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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getCondition(): ?Condition
    {
        return $this->condition;
    }

    public function setCondition(?Condition $condition): static
    {
        $this->condition = $condition;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getMeta(): ?array
    {
        return $this->meta;
    }

    /** @param array<string, mixed>|null $meta */
    public function setMeta(?array $meta): static
    {
        $this->meta = $meta;

        return $this;
    }
}
