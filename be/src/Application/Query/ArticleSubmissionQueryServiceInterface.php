<?php

declare(strict_types=1);

namespace App\Application\Query;

use App\Domain\Entity\Article;
use App\Domain\Entity\ArticleSubmission;

interface ArticleSubmissionQueryServiceInterface
{
    /**
     * @param Article[] $articles
     *
     * @return array<int, ArticleSubmission[]>
     */
    public function findAllByArticles(array $articles): array;
}
