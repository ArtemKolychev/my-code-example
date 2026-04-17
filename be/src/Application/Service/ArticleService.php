<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\DTO\Request\EditArticleRequest;
use App\Domain\Entity\Article;
use App\Domain\Entity\Image;
use App\Domain\Entity\User;
use App\Domain\Repository\ArticleRepositoryInterface;
use App\Domain\Repository\ImageRepositoryInterface;

class ArticleService
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly ImageRepositoryInterface $imageRepository,
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
     * }> $groups
     *
     * @return Article[]
     */
    public function createArticlesFromGroups(array $groups, User $user): array
    {
        $factory = new ArticleFactory($this->articleRepository, $this->imageService, $this->publicDirectory);

        return $factory->createFromGroups($groups, $user);
    }

    public function updateArticle(Article $article, EditArticleRequest $request): void
    {
        $article->setTitle($request->title);
        $article->setDescription($request->description);
        $article->setPrice($request->price);
        $this->articleRepository->save($article);
    }

    public function deleteArticle(Article $article): void
    {
        $this->articleRepository->remove($article);
    }

    public function deleteImage(Image $image): void
    {
        $link = $image->getLink() ?? '';
        $this->imageService->deleteImageFile($this->publicDirectory.'/'.$link);
        $this->imageRepository->remove($image);
    }
}
