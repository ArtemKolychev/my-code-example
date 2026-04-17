<?php

declare(strict_types=1);

namespace App\Tests\Shared\Mother;

use App\Domain\Entity\ArticleSubmission;
use App\Domain\Enum\Platform;
use App\Domain\ValueObject\SubmissionStatus;

/**
 * Object Mother for ArticleSubmission domain entities.
 *
 * All factory methods return real instances — no mocks.
 */
final class ArticleSubmissionMother
{
    public static function pending(Platform $platform = Platform::Seznam, string $jobId = 'test-job-pending'): ArticleSubmission
    {
        return self::withStatus($platform, SubmissionStatus::Pending, $jobId);
    }

    public static function failed(Platform $platform = Platform::Seznam, string $jobId = 'test-job-failed'): ArticleSubmission
    {
        return self::withStatus($platform, SubmissionStatus::Failed, $jobId);
    }

    public static function completed(Platform $platform = Platform::Seznam, string $jobId = 'test-job-completed'): ArticleSubmission
    {
        return self::withStatus($platform, SubmissionStatus::Completed, $jobId);
    }

    public static function deleting(Platform $platform = Platform::Seznam, string $jobId = 'test-job-deleting'): ArticleSubmission
    {
        return self::withStatus($platform, SubmissionStatus::Deleting, $jobId);
    }

    public static function withStatus(Platform $platform, SubmissionStatus $status, string $jobId = 'test-job'): ArticleSubmission
    {
        $submission = new ArticleSubmission();
        $submission->setPlatform($platform);
        $submission->setStatus($status);
        $submission->setJobId($jobId);

        return $submission;
    }
}
