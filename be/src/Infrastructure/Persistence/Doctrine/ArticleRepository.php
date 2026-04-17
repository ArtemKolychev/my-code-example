<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Entity\Article;
use App\Domain\Entity\User;
use App\Domain\Repository\ArticleRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository implements ArticleRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    public function findById(int $id): ?Article
    {
        return $this->find($id);
    }

    public function findByIdAndUser(int $id, User $user): ?Article
    {
        return $this->findOneBy(['id' => $id, 'user' => $user]);
    }

    /**
     * @param int[] $ids
     *
     * @return Article[]
     */
    public function findByIdsAndUser(array $ids, User $user): array
    {
        return $this->findBy(['id' => $ids, 'user' => $user]);
    }

    public function save(Article $article): void
    {
        $this->getEntityManager()->persist($article);
        $this->getEntityManager()->flush();
    }

    public function remove(Article $article): void
    {
        $this->getEntityManager()->remove($article);
        $this->getEntityManager()->flush();
    }
}
