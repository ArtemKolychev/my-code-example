<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Article;
use App\Domain\Entity\ArticleSubmission;
use App\Domain\Enum\Platform;

interface ArticleSubmissionRepositoryInterface
{
    public function findByJobId(string $jobId): ?ArticleSubmission;

    public function findByArticleAndPlatform(Article $article, Platform $platform): ?ArticleSubmission;

    /** @return ArticleSubmission[] */
    public function findPublishedByArticle(Article $article): array;

    public function countActiveByArticle(Article $article): int;

    /** @return ArticleSubmission[] */
    public function findAllByArticle(Article $article): array;

    /**
     * Batch load: returns all submissions for the given articles grouped by article ID.
     * Use this instead of calling findAllByArticle() inside a loop (N+1).
     *
     * @param Article[] $articles
     *
     * @return array<int, ArticleSubmission[]>
     */
    public function findAllByArticles(array $articles): array;

    public function save(ArticleSubmission $submission): void;
}
