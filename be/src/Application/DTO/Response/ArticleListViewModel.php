<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

use App\Domain\Entity\ArticleSubmission;

/** View model for the article list page. */
final readonly class ArticleListViewModel
{
    /**
     * @param ArticleReadModel[]                           $articles
     * @param array<int, array<string, ArticleSubmission>> $submissions Keyed [articleId][platform]
     */
    public function __construct(
        public array $articles,
        public array $submissions,
        public int $tokenBalance,
    ) {
    }
}
