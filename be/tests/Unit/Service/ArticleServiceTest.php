<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\Request\EditArticleRequest;
use App\Entity\Article;
use App\Entity\Image;
use App\Entity\User;
use App\Service\ArticleService;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;

class ArticleServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private ImageService&MockObject $imageService;
    private MessageBusInterface&MockObject $messageBus;
    private ArticleService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->imageService = $this->createMock(ImageService::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->service = new ArticleService(
            $this->em,
            $this->imageService,
            $this->messageBus,
            '/tmp/public',
        );
    }

    public function testCreateArticlesFromGroupsReturnsEmptyWhenNoGroups(): void
    {
        $user = $this->createMock(User::class);

        $result = $this->service->createArticlesFromGroups([], $user);

        $this->assertSame([], $result);
    }

    public function testUpdateArticleSetsFieldsAndFlushes(): void
    {
        $article = new Article();
        $article->setTitle('Old Title');
        $article->setDescription('Old Desc');

        $request = new EditArticleRequest(
            title: 'New Title',
            description: 'New Desc',
            price: 42.0,
        );

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->service->updateArticle($article, $request);

        $this->assertSame('New Title', $article->getTitle());
        $this->assertSame('New Desc', $article->getDescription());
        $this->assertSame(42.0, $article->getPrice());
    }

    public function testDeleteArticleRemovesAndFlushes(): void
    {
        $article = new Article();

        $this->em->expects($this->once())->method('remove')->with($article);
        $this->em->expects($this->once())->method('flush');

        $this->service->deleteArticle($article);
    }

    public function testDeleteImageRemovesEntityAndFile(): void
    {
        $image = new Image();
        $image->setLink('uploads/images/1/test.jpeg');

        $this->em->expects($this->once())->method('remove')->with($image);
        $this->imageService->expects($this->once())->method('deleteImageFile')
            ->with('/tmp/public/uploads/images/1/test.jpeg');
        $this->em->expects($this->once())->method('flush');

        $this->service->deleteImage($image);
    }
}
