<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Entity\Article;
use App\Domain\Entity\Image;
use App\Domain\Entity\User;
use App\Domain\Enum\Category;
use App\Domain\Enum\Condition;
use App\Domain\Repository\ArticleRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Creates Article entities from AI-generated grouping results.
 */
class ArticleFactory
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly ImageServiceInterface $imageService,
        private readonly string $publicDirectory,
    ) {
    }

    /**
     * @param array<array{
     *   group_name: string,
     *   description: string,
     *   images: string[],
     *   category?: string,
     *   condition?: string,
     *   extracted_fields?: array<string, mixed>,
     *   vehicleData?: array<string, mixed>
     * }> $groupedData
     *
     * @return Article[]
     */
    public function createFromGroups(array $groupedData, User $user): array
    {
        if (empty($groupedData)) {
            return [];
        }

        $imagesDirectory = $this->publicDirectory.'/'.ImageServiceInterface::IMAGES_DIRECTORY.'/';

        $newArticles = [];
        foreach ($groupedData as $articleData) {
            $article = new Article();
            $article->setTitle($articleData['group_name']);
            $article->setDescription($articleData['description']);

            $this->applyEnumsToArticle($article, $articleData);

            $meta = $this->buildArticleMeta($articleData);
            if (!empty($meta)) {
                $article->setMeta($meta);
            }

            // Persist to get the ID for image directory naming
            $this->articleRepository->save($article);

            $articleId = (string) $article->getId();
            $this->processArticleImages($article, $articleData['images'], $imagesDirectory, $articleId);

            $article->setUser($user);

            $newArticles[] = $article;
            $this->articleRepository->save($article);
        }

        return $newArticles;
    }

    /**
     * @param array{
     *   group_name: string,
     *   description: string,
     *   images: string[],
     *   category?: string,
     *   condition?: string,
     *   extracted_fields?: array<string, mixed>,
     *   vehicleData?: array<string, mixed>
     * } $articleData
     */
    private function applyEnumsToArticle(Article $article, array $articleData): void
    {
        if (!empty($articleData['category'])) {
            $category = Category::tryFrom($articleData['category']);
            if ($category) {
                $article->setCategory($category);
            }
        }

        if (!empty($articleData['condition'])) {
            $condition = Condition::tryFrom($articleData['condition']);
            if ($condition) {
                $article->setCondition($condition);
            }
        }
    }

    /**
     * @param array{
     *   group_name: string,
     *   description: string,
     *   images: string[],
     *   category?: string,
     *   condition?: string,
     *   extracted_fields?: array<string, mixed>,
     *   vehicleData?: array<string, mixed>
     * } $articleData
     *
     * @return array<string, mixed>
     */
    private function buildArticleMeta(array $articleData): array
    {
        $meta = [];
        if (!empty($articleData['extracted_fields'])) {
            $meta = $articleData['extracted_fields'];
        }
        if (!empty($articleData['vehicleData'])) {
            return array_merge($meta, $articleData['vehicleData']);
        }

        return $meta;
    }

    /**
     * @param string[] $images
     */
    private function processArticleImages(Article $article, array $images, string $imagesDirectory, string $articleId): void
    {
        $movedImages = array_map(
            fn (string $filePath): string => $this->imageService->moveImage(
                $this->publicDirectory.'/'.ltrim($filePath, '/'),
                $imagesDirectory.$articleId.'/'.basename($filePath),
            ),
            $images,
        );

        $imageEntities = [];
        foreach (array_values($movedImages) as $index => $imagePath) {
            $imageEntities[] = new Image()
                ->setLink(str_replace($this->publicDirectory, '', $imagePath))
                ->setArticle($article)
                ->setPosition($index);
        }
        $article->setImages(new ArrayCollection($imageEntities));
    }
}
