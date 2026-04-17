<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

use App\Domain\Entity\Article;
use App\Domain\Entity\Image;
use App\Domain\Enum\Category;
use App\Domain\Enum\Condition;
use DateTimeImmutable;

final readonly class ArticleReadModel
{
    public static function fromArticle(Article $article): self
    {
        $images = $article->getImages();
        $firstImage = $images->count() > 0 ? $images->first() : null;
        $firstImageLink = $firstImage instanceof Image ? $firstImage->link : null;

        return new self(
            id: $article->getId(),
            title: $article->getTitle(),
            description: $article->getDescription(),
            price: $article->getPrice(),
            category: $article->getCategory(),
            condition: $article->getCondition(),
            createdAt: $article->getCreatedAt(),
            withdrawnAt: $article->getWithdrawnAt(),
            pendingInput: $article->getPendingInput(),
            publicResultData: $article->getPublicResultData(),
            imageCount: $images->count(),
            firstImageLink: $firstImageLink,
        );
    }

    /**
     * @param array<string, mixed>|null $publicResultData
     * @param array<string, mixed>|null $pendingInput
     */
    public function __construct(
        public ?int $id,
        public ?string $title,
        public ?string $description,
        public ?float $price,
        public ?Category $category,
        public ?Condition $condition,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $withdrawnAt,
        public ?array $pendingInput,
        public ?array $publicResultData,
        public int $imageCount,
        public ?string $firstImageLink,
    ) {
    }
}
