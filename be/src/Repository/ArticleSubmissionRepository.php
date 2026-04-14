<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Article;
use App\Entity\ArticleSubmission;
use App\Enum\Platform;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArticleSubmission>
 */
class ArticleSubmissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleSubmission::class);
    }

    public function findByJobId(string $jobId): ?ArticleSubmission
    {
        return $this->findOneBy(['jobId' => $jobId]);
    }

    public function findByArticleAndPlatform(Article $article, Platform $platform): ?ArticleSubmission
    {
        return $this->findOneBy(['article' => $article, 'platform' => $platform]);
    }

    /**
     * Returns all successfully published submissions for an article that have an ad URL.
     *
     * @return ArticleSubmission[]
     */
    public function findPublishedByArticle(Article $article): array
    {
        return array_values(array_filter(
            $this->findBy(['article' => $article]),
            static function (ArticleSubmission $s): bool {
                if ('completed' !== $s->getStatus()) {
                    return false;
                }
                $data = $s->getResultData();

                return !empty($data['articleUrl']) || !empty($data['adUrl']);
            }
        ));
    }

    public function countActiveByArticle(Article $article): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.article = :article')
            ->andWhere('s.status IN (:statuses)')
            ->setParameter('article', $article)
            ->setParameter('statuses', ['completed', 'deleting'])
            ->getQuery()
            ->getSingleScalarResult();
    }
}
