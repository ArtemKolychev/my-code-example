<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

use App\Domain\Entity\ImageBatch;
use App\Domain\ValueObject\BatchStatus;

final readonly class BatchReadModel
{
    public static function fromBatch(ImageBatch $batch): self
    {
        return new self(
            id: (int) $batch->getId(),
            status: $batch->getStatus(),
            articleIds: $batch->getArticleIds(),
            pendingInput: $batch->pendingInput,
            imagePaths: $batch->getImagePaths(),
        );
    }

    /**
     * @param int[]|null                $articleIds
     * @param array<string, mixed>|null $pendingInput
     * @param string[]                  $imagePaths
     */
    public function __construct(
        public int $id,
        public BatchStatus $status,
        public ?array $articleIds,
        public ?array $pendingInput,
        public array $imagePaths,
    ) {
    }
}
