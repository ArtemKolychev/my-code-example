<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Entity\Article;
use App\Domain\Entity\ArticleSubmission;
use App\Domain\Enum\Platform;
use App\Domain\Repository\ArticleSubmissionRepositoryInterface;
use App\Domain\ValueObject\SubmissionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArticleSubmission>
 */
class ArticleSubmissionRepository extends ServiceEntityRepository implements ArticleSubmissionRepositoryInterface
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
     * @return ArticleSubmission[]
     */
    public function findAllByArticle(Article $article): array
    {
        return $this->findBy(['article' => $article]);
    }

    /**
     * @param Article[] $articles
     *
     * @return array<int, ArticleSubmission[]>
     */
    public function findAllByArticles(array $articles): array
    {
        if ($articles === []) {
            return [];
        }

        /** @var ArticleSubmission[] $submissions */
        $submissions = $this->createQueryBuilder('s')
            ->where('s.article IN (:articles)')
            ->setParameter('articles', $articles)
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($submissions as $submission) {
            $articleId = $submission->getArticle()?->getId();
            if ($articleId === null) {
                continue;
            }

            $grouped[$articleId][] = $submission;
        }

        /** @var array<int, ArticleSubmission[]> $grouped */
        return $grouped;
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
                if (SubmissionStatus::Completed !== $s->getStatus()) {
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
            ->setParameter('statuses', [SubmissionStatus::Completed->value, SubmissionStatus::Deleting->value])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function save(ArticleSubmission $submission): void
    {
        $this->getEntityManager()->persist($submission);
        $this->getEntityManager()->flush();
    }
}
