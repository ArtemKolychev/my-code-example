<?php

declare(strict_types=1);

namespace App\Infrastructure\Query;

use App\Application\Query\ArticleSubmissionQueryServiceInterface;
use App\Domain\Repository\ArticleSubmissionRepositoryInterface;

final readonly class DoctrineArticleSubmissionQuery implements ArticleSubmissionQueryServiceInterface
{
    public function __construct(
        private ArticleSubmissionRepositoryInterface $articleSubmissionRepository,
    ) {
    }

    public function findAllByArticles(array $articles): array
    {
        return $this->articleSubmissionRepository->findAllByArticles($articles);
    }
}
