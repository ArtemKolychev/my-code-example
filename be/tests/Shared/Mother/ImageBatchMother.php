<?php

declare(strict_types=1);

namespace App\Tests\Shared\Mother;

use App\Domain\Entity\ImageBatch;
use App\Domain\Entity\User;
use App\Domain\ValueObject\BatchStatus;

/**
 * Object Mother for ImageBatch domain entities.
 *
 * All factory methods return real instances — no mocks.
 */
final class ImageBatchMother
{
    /**
     * An ImageBatch with a controlled ID and job ID.
     * Callers can pass a specific User; defaults to UserMother::withoutCredential().
     */
    public static function withId(int $id, string $jobId = 'batch-job-1', ?User $user = null): ImageBatch
    {
        $batch = new ImageBatch();
        $batch->id = $id;
        $batch->jobId = $jobId;
        $batch->user = $user ?? UserMother::withoutCredential();

        return $batch;
    }

    /**
     * A minimal pending ImageBatch with a random ID.
     */
    public static function any(?User $user = null): ImageBatch
    {
        $batch = new ImageBatch();
        $batch->id = random_int(1, 1000);
        $batch->jobId = 'batch-job-'.uniqid();
        $batch->user = $user ?? UserMother::withoutCredential();
        $batch->status = BatchStatus::Pending;

        return $batch;
    }
}
