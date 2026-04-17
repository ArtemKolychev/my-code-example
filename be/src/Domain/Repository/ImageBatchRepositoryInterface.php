<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\ImageBatch;
use App\Domain\Entity\User;

interface ImageBatchRepositoryInterface
{
    public function findById(int $id): ?ImageBatch;

    public function findByJobId(string $jobId): ?ImageBatch;

    public function findByIdAndUser(int $id, User $user): ?ImageBatch;

    public function save(ImageBatch $batch): void;

    /** @param array<string, mixed> $pendingInput */
    public function markNeedsInput(int $batchId, array $pendingInput): void;
}
