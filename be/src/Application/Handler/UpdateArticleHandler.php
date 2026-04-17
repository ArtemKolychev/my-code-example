<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\UpdateArticleCommand;
use App\Application\Service\ImageServiceInterface;
use App\Domain\Entity\Image;
use App\Domain\Exception\ArticleNotFoundException;
use App\Domain\Repository\ArticleRepositoryInterface;
use App\Domain\Repository\ImageRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: UpdateArticleCommand::class)]
final readonly class UpdateArticleHandler
{
    public function __construct(
        private ArticleRepositoryInterface $articleRepository,
        private ImageRepositoryInterface $imageRepository,
        private ImageServiceInterface $imageService,
        private string $publicDirectory,
    ) {
    }

    public function __invoke(UpdateArticleCommand $command): void
    {
        $article = $this->articleRepository->findById($command->articleId);
        if (!$article) {
            throw ArticleNotFoundException::forId($command->articleId);
        }

        if (null !== $command->title) {
            $article->setTitle($command->title);
        }
        if (null !== $command->description) {
            $article->setDescription($command->description);
        }
        $article->setPrice($command->price);

        if (null !== $command->category) {
            $article->setCategory($command->category);
        }
        if (null !== $command->condition) {
            $article->setCondition($command->condition);
        }
        if (null !== $command->metaFields) {
            $current = $article->getMeta() ?? [];
            $article->setMeta(array_merge($current, $command->metaFields));
        }

        $imagesDirectory = $this->publicDirectory.'/'.ImageServiceInterface::IMAGES_DIRECTORY.'/';
        foreach ($command->images as $tmpImage) {
            $imagePath = $this->imageService->uploadImage($tmpImage, $imagesDirectory.$article->getId());
            $img = new Image()->setLink($imagePath)->setArticle($article);
            $this->imageRepository->save($img);
        }

        $this->articleRepository->save($article);
    }
}
