<?php

declare(strict_types=1);

namespace App\Application\Query;

use App\Application\DTO\Response\BatchReadModel;
use App\Domain\Entity\User;

interface BatchProviderInterface
{
    public function findForUser(int $batchId, User $user): ?BatchReadModel;

    /**
     * @return array{status: string, articleIds: int[]|null, pendingInput: array<string, mixed>|null}
     */
    public function getStatus(BatchReadModel $batch): array;
}
