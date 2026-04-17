<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Application\DTO\Request\EditArticleRequest;
use App\Application\Service\ArticleService;
use App\Application\Service\ImageServiceInterface;
use App\Domain\Entity\User;
use App\Domain\Repository\ArticleRepositoryInterface;
use App\Domain\Repository\ImageRepositoryInterface;
use App\Tests\Shared\Mother\ArticleMother;
use App\Tests\Shared\Mother\ImageMother;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ArticleServiceTest extends TestCase
{
    private ArticleRepositoryInterface&MockObject $articleRepository;
    private ImageRepositoryInterface&MockObject $imageRepository;
    private ImageServiceInterface&MockObject $imageService;
    private ArticleService $service;

    public function testCreateArticlesFromGroupsReturnsEmptyWhenNoGroups(): void
    {
        $user = $this->createMock(User::class);

        $result = $this->service->createArticlesFromGroups([], $user);

        $this->assertSame([], $result);
    }

    public function testUpdateArticleSetsFieldsAndFlushes(): void
    {
        $article = ArticleMother::any();
        $article->setTitle('Old Title');
        $article->setDescription('Old Desc');

        $request = new EditArticleRequest(
            title: 'New Title',
            description: 'New Desc',
            price: 42.0,
        );

        $this->articleRepository->expects($this->once())->method('save')->with($article);

        $this->service->updateArticle($article, $request);

        $this->assertSame('New Title', $article->getTitle());
        $this->assertSame('New Desc', $article->getDescription());
        $this->assertSame(42.0, $article->getPrice());
    }

    public function testDeleteArticleRemovesAndFlushes(): void
    {
        $article = ArticleMother::any();

        $this->articleRepository->expects($this->once())->method('remove')->with($article);

        $this->service->deleteArticle($article);
    }

    public function testDeleteImageRemovesEntityAndFile(): void
    {
        $image = ImageMother::any();
        $image->setLink('uploads/images/1/test.jpeg');

        $this->imageRepository->expects($this->once())->method('remove')->with($image);
        $this->imageService->expects($this->once())->method('deleteImageFile')
            ->with('/tmp/public/uploads/images/1/test.jpeg');

        $this->service->deleteImage($image);
    }

    protected function setUp(): void
    {
        $this->articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $this->imageRepository = $this->createMock(ImageRepositoryInterface::class);
        $this->imageService = $this->createMock(ImageServiceInterface::class);

        $this->service = new ArticleService(
            $this->articleRepository,
            $this->imageRepository,
            $this->imageService,
            '/tmp/public',
        );
    }
}
