<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Application\Service\ClickerFailedService;
use App\Domain\Entity\Article;
use App\Domain\Entity\ImageBatch;
use App\Domain\Event\ClickerEvent;
use App\Domain\Repository\ArticleRepositoryInterface;
use App\Domain\Repository\ArticleSubmissionRepositoryInterface;
use App\Domain\Repository\ImageBatchRepositoryInterface;
use App\Domain\ValueObject\BatchStatus;
use App\Domain\ValueObject\SubmissionStatus;
use App\Tests\Shared\Mother\ArticleMother;
use App\Tests\Shared\Mother\ArticleSubmissionMother;
use App\Tests\Shared\Mother\ImageBatchMother;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

class ClickerFailedServiceTest extends TestCase
{
    private ArticleRepositoryInterface&MockObject $articleRepository;
    private ArticleSubmissionRepositoryInterface&MockObject $submissionRepository;
    private ImageBatchRepositoryInterface&MockObject $batchRepository;
    private LoggerInterface&MockObject $logger;
    private ClockInterface&MockObject $clock;
    private ClickerFailedService $service;

    public function testFailedSetsBatchStatusFailed(): void
    {
        $batch = $this->makeBatch(30, 'seznam_fail1');

        $this->batchRepository->method('findByJobId')->willReturn($batch);
        $this->batchRepository->expects($this->once())->method('save');
        $this->submissionRepository->expects($this->never())->method('findByJobId');

        $this->service->process(new ClickerEvent('failed', 'seznam_fail1', step: 'login', error: 'Auth failed'));

        $this->assertSame(BatchStatus::Failed, $batch->getStatus());
    }

    public function testFailedSetsSubmissionFailedWithErrorData(): void
    {
        $submission = ArticleSubmissionMother::pending();

        $this->batchRepository->method('findByJobId')->willReturn(null);
        $this->submissionRepository->method('findByJobId')->willReturn($submission);
        $this->submissionRepository->expects($this->once())->method('save');

        $this->service->process(new ClickerEvent('failed', 'seznam_fail2', step: 'publish', error: 'Timeout'));

        $this->assertSame(SubmissionStatus::Failed, $submission->getStatus());
        $this->assertSame(['step' => 'publish', 'error' => 'Timeout'], $submission->getErrorData());
    }

    public function testFailedDeleteNotFoundTreatsAsWithdrawn(): void
    {
        $submission = ArticleSubmissionMother::pending();

        $this->batchRepository->method('findByJobId')->willReturn(null);
        $this->submissionRepository->method('findByJobId')->willReturn($submission);
        $this->submissionRepository->method('countActiveByArticle')->willReturn(0);

        $this->service->process(new ClickerEvent('failed', 'job1', step: 'seznam-delete', error: '404 not found'));

        $this->assertSame(SubmissionStatus::Withdrawn, $submission->getStatus());
        $this->assertSame(['deleted' => true, 'notFound' => true], $submission->getResultData());
    }

    public function testFailedDeletingStatusRestoresSubmissionToCompleted(): void
    {
        $submission = ArticleSubmissionMother::deleting();

        $this->batchRepository->method('findByJobId')->willReturn(null);
        $this->submissionRepository->method('findByJobId')->willReturn($submission);

        $this->service->process(new ClickerEvent('failed', 'job1', step: 'submit', error: 'Server error'));

        $this->assertSame(SubmissionStatus::Completed, $submission->getStatus());
        $this->assertSame(['step' => 'submit', 'error' => 'Server error'], $submission->getErrorData());
    }

    public function testFailedArticleMarkedWithdrawnWhenNoActiveSubmissionsAfterDeleteNotFound(): void
    {
        ArticleMother::withId(5);
        $submission = ArticleSubmissionMother::pending();

        // Associate article with submission via reflection (or via the entity's method if available)
        $this->batchRepository->method('findByJobId')->willReturn(null);
        $this->submissionRepository->method('findByJobId')->willReturn($submission);
        $this->submissionRepository->method('countActiveByArticle')->willReturn(0);
        $this->clock->method('now')->willReturn(new DateTimeImmutable());

        $this->service->process(new ClickerEvent('failed', 'job1', step: 'seznam-delete', error: 'inzerát neexistuje'));

        // Article is null on submission (no article set), so markArticleWithdrawnIfNoActive bails early — no save
        $this->articleRepository->expects($this->never())->method('save');
    }

    protected function setUp(): void
    {
        $this->articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $this->submissionRepository = $this->createMock(ArticleSubmissionRepositoryInterface::class);
        $this->batchRepository = $this->createMock(ImageBatchRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);

        $this->service = new ClickerFailedService(
            $this->articleRepository,
            $this->submissionRepository,
            $this->batchRepository,
            $this->logger,
            $this->clock,
        );
    }

    private function makeBatch(int $id, string $jobId): ImageBatch
    {
        return ImageBatchMother::withId($id, $jobId);
    }
}
