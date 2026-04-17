<?php

declare(strict_types=1);

namespace App\Application\Query;

use App\Domain\Entity\Article;
use App\Domain\Entity\User;
use App\Domain\Repository\ArticleRepositoryInterface;

final readonly class ArticlesByIdsProvider
{
    public function __construct(
        private ArticleRepositoryInterface $articleRepository,
    ) {
    }

    /**
     * @param int[] $ids
     *
     * @return Article[]
     */
    public function findByIdsAndUser(array $ids, User $user): array
    {
        return $this->articleRepository->findByIdsAndUser($ids, $user);
    }
}
