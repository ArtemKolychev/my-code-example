<?php

declare(strict_types=1);

namespace App\DTO\Response;

use App\Entity\Article;
use App\Entity\ArticleSubmission;

class ArticleResponse
{
    /**
     * @param array<int, array{id: int|null, link: string|null}>            $images
     * @param array<string, mixed>|null                                     $publicResultData
     * @param array<int, array{name: string, url: string}>|null             $priceSources
     * @param array<string, mixed>|null                                     $pendingInput
     * @param array<string, array{status: string, articleUrl: string|null}> $submissions
     */
    public function __construct(
        public readonly ?int $id,
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly ?float $price,
        public readonly ?string $priceReasoning,
        public readonly ?array $priceSources,
        public readonly ?array $publicResultData,
        public readonly array $images,
        public readonly ?array $pendingInput,
        public readonly array $submissions,
        public readonly ?string $condition = null,
    ) {
    }

    /**
     * @param ArticleSubmission[] $submissions
     */
    public static function fromEntity(Article $article, array $submissions = []): self
    {
        $images = [];
        foreach ($article->getImages() as $image) {
            $images[] = [
                'id' => $image->getId(),
                'link' => $image->getLink(),
            ];
        }

        $submissionsMap = [];
        foreach ($submissions as $submission) {
            $resultData = $submission->getResultData();
            $submissionsMap[$submission->getPlatform()->value] = [
                'status' => $submission->getStatus(),
                'articleUrl' => $resultData['articleUrl'] ?? $resultData['adUrl'] ?? null,
                'progressData' => $submission->getProgressData(),
            ];
        }

        return new self(
            id: $article->getId(),
            title: $article->getTitle(),
            description: $article->getDescription(),
            price: $article->getPrice(),
            priceReasoning: $article->getPriceReasoning(),
            priceSources: $article->getPriceSources(),
            publicResultData: $article->getPublicResultData(),
            images: $images,
            pendingInput: $article->getPendingInput(),
            submissions: $submissionsMap,
            condition: $article->condition?->label(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'price' => $this->price,
            'priceReasoning' => $this->priceReasoning,
            'priceSources' => $this->priceSources,
            'publicResultData' => $this->publicResultData,
            'images' => $this->images,
            'pendingInput' => $this->pendingInput,
            'submissions' => $this->submissions,
            'condition' => $this->condition,
        ];
    }
}
