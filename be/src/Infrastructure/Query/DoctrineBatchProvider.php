<?php

declare(strict_types=1);

namespace App\Infrastructure\Query;

use App\Application\DTO\Response\BatchReadModel;
use App\Application\Query\BatchProviderInterface;
use App\Domain\Entity\User;
use App\Domain\Repository\ImageBatchRepositoryInterface;

final readonly class DoctrineBatchProvider implements BatchProviderInterface
{
    public function __construct(
        private ImageBatchRepositoryInterface $imageBatchRepository,
    ) {
    }

    public function findForUser(int $batchId, User $user): ?BatchReadModel
    {
        $batch = $this->imageBatchRepository->findByIdAndUser($batchId, $user);

        return $batch ? BatchReadModel::fromBatch($batch) : null;
    }

    /**
     * @return array{status: string, articleIds: int[]|null, pendingInput: array<string, mixed>|null}
     */
    public function getStatus(BatchReadModel $batch): array
    {
        $pending = $batch->pendingInput;
        if ($pending) {
            $toUrl = static fn (string $p): string => str_starts_with($p, '/') ? $p : '/'.$p;
            if ('group_conditions' === ($pending['inputType'] ?? '')) {
                /** @var array<int, array<string, mixed>> $pendingGroups */
                $pendingGroups = $pending['groups'] ?? [];
                foreach ($pendingGroups as &$group) {
                    /** @var string[] $groupImages */
                    $groupImages = $group['images'] ?? [];
                    $group['imageUrls'] = array_map($toUrl, $groupImages);
                }
                unset($group);
                $pending['groups'] = $pendingGroups;
            } elseif (!isset($pending['imageUrls'])) {
                $pending['imageUrls'] = array_map($toUrl, $batch->imagePaths);
            }
        }

        return [
            'status' => $batch->status->value,
            'articleIds' => $batch->articleIds,
            'pendingInput' => $pending,
        ];
    }
}
