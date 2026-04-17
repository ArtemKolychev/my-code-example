<?php

declare(strict_types=1);

namespace App\Tests\Unit\Handler;

use App\Application\Command\UpdateArticleCommand;
use App\Application\Handler\UpdateArticleHandler;
use App\Application\Service\ImageServiceInterface;
use App\Domain\Entity\Image;
use App\Domain\Exception\ArticleNotFoundException;
use App\Domain\Repository\ArticleRepositoryInterface;
use App\Domain\Repository\ImageRepositoryInterface;
use App\Tests\Shared\Mother\ArticleMother;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * I am testing UpdateArticleHandler at the Unit level.
 * Double strategy: Mock for repositories and ImageServiceInterface.
 * Behavior to verify: partial field updates, null-safety on title/description,
 * image persistence, and ArticleNotFoundException on missing article.
 */
final class UpdateArticleHandlerTest extends TestCase
{
    private ArticleRepositoryInterface&MockObject $articleRepository;
    private ImageRepositoryInterface&MockObject $imageRepository;
    private ImageServiceInterface&MockObject $imageService;

    private UpdateArticleHandler $handler;

    /** @return array<string, array{?string, ?string, string, string}> */
    public static function nullFieldPreservationProvider(): array
    {
        return [
            'null title → original title preserved' => [null,        'New desc',  'Original title',       'New desc'],
            'null description → original description preserved' => ['New title',  null,        'New title',            'Original description'],
        ];
    }

    // -------------------------------------------------------------------------
    // Exception path
    // -------------------------------------------------------------------------

    public function testThrowsArticleNotFoundExceptionWhenArticleMissing(): void
    {
        $articleId = random_int(1, 1000);
        $this->articleRepository->method('findById')->willReturn(null);

        $this->expectException(ArticleNotFoundException::class);
        $this->expectExceptionMessage("Article id:[{$articleId}] not found");

        ($this->handler)(new UpdateArticleCommand($articleId, 'New title', null, null));
    }

    // -------------------------------------------------------------------------
    // Field update behavior
    // -------------------------------------------------------------------------

    public function testUpdatesAllProvidedFields(): void
    {
        $article = ArticleMother::any();
        $article->setTitle('Original title');
        $article->setDescription('Original description');
        $article->setPrice(100.0);

        $this->articleRepository->method('findById')->willReturn($article);
        $this->articleRepository->expects($this->once())->method('save')->with($article);

        ($this->handler)(new UpdateArticleCommand($article->getId() ?? 1, 'New title', 'New description', 250.0));

        $this->assertSame('New title', $article->getTitle());
        $this->assertSame('New description', $article->getDescription());
        $this->assertSame(250.0, $article->getPrice());
    }

    /**
     * Passing null for a field must preserve the original value — postArticlesHandler must not overwrite with null.
     */
    #[DataProvider('nullFieldPreservationProvider')]
    public function testNullFieldPreservesOriginalValue(
        ?string $commandTitle,
        ?string $commandDescription,
        string $expectedTitle,
        string $expectedDescription,
    ): void {
        $article = ArticleMother::any();
        $article->setTitle('Original title');
        $article->setDescription('Original description');

        $this->articleRepository->method('findById')->willReturn($article);
        $this->articleRepository->method('save');

        ($this->handler)(new UpdateArticleCommand($article->getId() ?? 1, $commandTitle, $commandDescription, 50.0));

        $this->assertSame($expectedTitle, $article->getTitle());
        $this->assertSame($expectedDescription, $article->getDescription());
    }

    public function testAlwaysUpdatesPriceEvenToNull(): void
    {
        $article = ArticleMother::any();
        $article->setPrice(100.0);

        $this->articleRepository->method('findById')->willReturn($article);
        $this->articleRepository->method('save');

        ($this->handler)(new UpdateArticleCommand($article->getId() ?? 1, null, null, null));

        $this->assertNull($article->getPrice());
    }

    // -------------------------------------------------------------------------
    // Image handling
    // -------------------------------------------------------------------------

    public function testSavesEachUploadedImageViaImageRepository(): void
    {
        $article = ArticleMother::any();

        $this->articleRepository->method('findById')->willReturn($article);
        $this->articleRepository->method('save');
        $this->imageService->method('uploadImage')->willReturn('/uploads/images/1/photo.jpg');

        $this->imageRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (mixed $img): bool {
                assert($img instanceof Image);

                return '/uploads/images/1/photo.jpg' === $img->getLink();
            }));

        $tmpFile = tempnam(sys_get_temp_dir(), 'phpunit-img-');
        file_put_contents((string) $tmpFile, 'fake-image-content');
        $uploadedFile = new UploadedFile((string) $tmpFile, 'photo.jpg', 'image/jpeg', null, true);

        ($this->handler)(new UpdateArticleCommand($article->getId() ?? 1, null, null, null, images: [$uploadedFile]));

        unlink((string) $tmpFile);
    }

    public function testSavesArticleAfterAllImagesAreProcessed(): void
    {
        $article = ArticleMother::any();

        $this->articleRepository->method('findById')->willReturn($article);
        $this->imageService->method('uploadImage')->willReturn('/uploads/images/1/a.jpg');
        $this->imageRepository->method('save');
        $this->articleRepository->expects($this->once())->method('save')->with($article);

        $tmpFile = tempnam(sys_get_temp_dir(), 'phpunit-img-');
        file_put_contents((string) $tmpFile, 'fake');
        $file = new UploadedFile((string) $tmpFile, 'a.jpg', 'image/jpeg', null, true);

        ($this->handler)(new UpdateArticleCommand($article->getId() ?? 1, null, null, null, images: [$file]));

        unlink((string) $tmpFile);
    }

    public function testNoImagesToSaveWhenCommandImagesIsEmpty(): void
    {
        $article = ArticleMother::any();

        $this->articleRepository->method('findById')->willReturn($article);
        $this->imageRepository->expects($this->never())->method('save');
        $this->imageService->expects($this->never())->method('uploadImage');
        $this->articleRepository->expects($this->once())->method('save');

        ($this->handler)(new UpdateArticleCommand($article->getId() ?? 1, 'Title', 'Desc', 99.0));
    }

    protected function setUp(): void
    {
        $this->articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $this->imageRepository = $this->createMock(ImageRepositoryInterface::class);
        $this->imageService = $this->createMock(ImageServiceInterface::class);

        $this->handler = new UpdateArticleHandler(
            $this->articleRepository,
            $this->imageRepository,
            $this->imageService,
            publicDirectory: '/tmp/public',
        );
    }
}
