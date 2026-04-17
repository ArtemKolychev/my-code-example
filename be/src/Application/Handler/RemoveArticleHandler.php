<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\RemoveArticleCommand;
use App\Domain\Exception\ArticleNotFoundException;
use App\Domain\Repository\ArticleRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: RemoveArticleCommand::class)]
final readonly class RemoveArticleHandler
{
    public function __construct(
        private ArticleRepositoryInterface $articleRepository,
    ) {
    }

    public function __invoke(RemoveArticleCommand $command): void
    {
        $article = $this->articleRepository->findById($command->articleId);

        if (!$article) {
            throw ArticleNotFoundException::forId($command->articleId);
        }

        $this->articleRepository->remove($article);
    }
}
