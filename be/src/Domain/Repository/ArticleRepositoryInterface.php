<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Article;
use App\Domain\Entity\User;

interface ArticleRepositoryInterface
{
    public function findById(int $id): ?Article;

    public function findByIdAndUser(int $id, User $user): ?Article;

    /**
     * @param int[] $ids
     *
     * @return Article[]
     */
    public function findByIdsAndUser(array $ids, User $user): array;

    public function save(Article $article): void;

    public function remove(Article $article): void;
}
