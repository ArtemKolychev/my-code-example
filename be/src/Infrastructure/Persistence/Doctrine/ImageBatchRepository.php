<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Domain\Entity\ImageBatch;
use App\Domain\Entity\User;
use App\Domain\Repository\ImageBatchRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ImageBatch>
 */
class ImageBatchRepository extends ServiceEntityRepository implements ImageBatchRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImageBatch::class);
    }

    public function findById(int $id): ?ImageBatch
    {
        return $this->find($id);
    }

    public function findByIdAndUser(int $id, User $user): ?ImageBatch
    {
        return $this->findOneBy(['id' => $id, 'user' => $user]);
    }

    public function findByJobId(string $jobId): ?ImageBatch
    {
        return $this->findOneBy(['jobId' => $jobId]);
    }

    public function save(ImageBatch $batch): void
    {
        $this->getEntityManager()->persist($batch);
        $this->getEntityManager()->flush();
    }

    /**
     * @param array<string, mixed> $pendingInput
     *
     * @throws Exception
     */
    public function markNeedsInput(int $batchId, array $pendingInput): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE image_batch SET status = :status, pending_input = :pendingInput WHERE id = :id',
            [
                'status' => 'needs_input',
                'pendingInput' => json_encode($pendingInput),
                'id' => $batchId,
            ]
        );
    }
}
