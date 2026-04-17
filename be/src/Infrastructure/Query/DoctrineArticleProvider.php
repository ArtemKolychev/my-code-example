<?php

declare(strict_types=1);

namespace App\Infrastructure\Query;

use App\Application\DTO\Response\ArticleResponse;
use App\Application\Query\ArticleProviderInterface;
use App\Domain\Entity\Article;
use App\Domain\Entity\User;
use App\Domain\Repository\ArticleRepositoryInterface;
use App\Domain\Repository\ArticleSubmissionRepositoryInterface;

final readonly class DoctrineArticleProvider implements ArticleProviderInterface
{
    public function __construct(
        private ArticleRepositoryInterface $articleRepository,
        private ArticleSubmissionRepositoryInterface $articleSubmissionRepository,
    ) {
    }

    public function findForUser(int $id, User $user): ?ArticleResponse
    {
        $article = $this->articleRepository->findByIdAndUser($id, $user);
        if (!$article) {
            return null;
        }

        return ArticleResponse::fromEntity(
            $article,
            $this->articleSubmissionRepository->findAllByArticle($article),
        );
    }

    /** @return ArticleResponse[] */
    public function listForUser(User $user): array
    {
        $articles = $user->getArticles()->toArray();
        $submissionsByArticle = $this->articleSubmissionRepository->findAllByArticles($articles);

        return array_map(
            static fn (Article $article): ArticleResponse => ArticleResponse::fromEntity(
                $article,
                $submissionsByArticle[$article->getId()] ?? [],
            ),
            $articles,
        );
    }
}
