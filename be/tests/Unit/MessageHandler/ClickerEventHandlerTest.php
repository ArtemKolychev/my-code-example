<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Application\Handler\ClickerEventHandler;
use App\Application\Service\ClickerFailedService;
use App\Application\Service\ClickerStandardCompletedService;
use App\Application\Service\EnrichVehicleCompletedService;
use App\Application\Service\GroupImagesCompletedService;
use App\Application\Service\SuggestPriceCompletedService;
use App\Domain\Entity\Article;
use App\Domain\Entity\ArticleSubmission;
use App\Domain\Entity\ImageBatch;
use App\Domain\Enum\Platform;
use App\Domain\Event\ClickerEvent;
use App\Domain\Repository\ArticleRepositoryInterface;
use App\Domain\Repository\ArticleSubmissionRepositoryInterface;
use App\Domain\Repository\ImageBatchRepositoryInterface;
use App\Domain\ValueObject\SubmissionStatus;
use App\Tests\Shared\Mother\ArticleMother;
use App\Tests\Shared\Mother\ArticleSubmissionMother;
use App\Tests\Shared\Mother\ImageBatchMother;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ClickerEventHandlerTest extends TestCase
{
    private ArticleRepositoryInterface&MockObject $articleRepository;
    private ArticleSubmissionRepositoryInterface&MockObject $submissionRepository;
    private ImageBatchRepositoryInterface&MockObject $batchRepository;
    private LoggerInterface&MockObject $logger;
    private GroupImagesCompletedService&MockObject $groupImagesService;
    private SuggestPriceCompletedService&MockObject $suggestPriceService;
    private EnrichVehicleCompletedService&MockObject $enrichVehicleService;
    private ClickerStandardCompletedService&MockObject $standardCompletedService;
    private ClickerFailedService&MockObject $failedService;
    private ClickerEventHandler $handler;

    // --- progress ---

    public function testProgressEventOnlyLogs(): void
    {
        $event = new ClickerEvent('progress', 'seznam_job1', step: 'fill_form');

        $this->logger->expects($this->atLeastOnce())->method('info');

        ($this->handler)($event);
    }

    // --- unknown type ---

    public function testUnknownEventTypeLogsWarning(): void
    {
        $event = new ClickerEvent('unknown_type', 'seznam_job1');

        $this->logger->expects($this->atLeastOnce())->method('warning');

        ($this->handler)($event);
    }

    // --- needs_input with articleId ---

    public function testNeedsInputWithArticleIdSavesPendingInputToArticle(): void
    {
        $article = $this->makeArticle(5);
        $submission = $this->makeSubmission();

        $this->articleRepository->method('findById')->with(5)->willReturn($article);
        $this->submissionRepository->method('findByJobId')->willReturn($submission);
        $this->submissionRepository->expects($this->once())->method('save');
        $this->articleRepository->expects($this->once())->method('save');

        $event = new ClickerEvent(
            'needs_input',
            'seznam_job1',
            inputType: 'code',
            inputPrompt: 'Enter SMS code',
            articleId: 5,
        );

        ($this->handler)($event);

        $pending = $article->getPendingInput();
        $this->assertNotNull($pending);
        $this->assertSame('code', $pending['inputType']);
        $this->assertSame('seznam_job1', $pending['jobId']);
        $this->assertSame(SubmissionStatus::Processing, $submission->getStatus());
    }

    public function testNeedsInputWithArticleIdLogsErrorWhenArticleNotFound(): void
    {
        $this->articleRepository->method('findById')->willReturn(null);

        $this->logger->expects($this->atLeastOnce())->method('error');

        $event = new ClickerEvent('needs_input', 'seznam_job1', articleId: 999);

        ($this->handler)($event);
    }

    // --- needs_input without articleId (batch path) ---

    public function testNeedsInputWithoutArticleIdUpdatesBatchViaSql(): void
    {
        $batch = $this->makeBatch(10, 'seznam_job2');

        $this->batchRepository->method('findByJobId')->willReturn($batch);
        $this->batchRepository->expects($this->once())->method('markNeedsInput');

        $event = new ClickerEvent(
            'needs_input',
            'seznam_job2',
            inputType: 'category',
            inputPrompt: 'Choose category',
        );

        ($this->handler)($event);
    }

    public function testNeedsInputWithoutArticleIdLogsWarningWhenNoBatchFound(): void
    {
        $this->batchRepository->method('findByJobId')->willReturn(null);

        $this->logger->expects($this->atLeastOnce())->method('warning');

        $event = new ClickerEvent('needs_input', 'seznam_job3');

        ($this->handler)($event);
    }

    // --- completed: standard (publish/delete) ---

    public function testCompletedPublishDelegatesToStandardCompletedService(): void
    {
        $result = ['action' => 'publish', 'articleId' => 7, 'isOk' => true];
        $event = new ClickerEvent('completed', 'seznam_job1', result: $result);

        $this->standardCompletedService->expects($this->once())
            ->method('process')
            ->with($event, $result, 'publish');

        ($this->handler)($event);
    }

    public function testCompletedDeleteDelegatesToStandardCompletedService(): void
    {
        $result = ['action' => 'delete', 'articleId' => 11];
        $event = new ClickerEvent('completed', 'seznam_job1', result: $result);

        $this->standardCompletedService->expects($this->once())
            ->method('process')
            ->with($event, $result, 'delete');

        ($this->handler)($event);
    }

    // --- completed: suggest_price ---

    public function testCompletedSuggestPriceDelegatesToService(): void
    {
        $result = ['action' => 'suggest_price', 'articleId' => 12, 'price' => 4990.0];

        $this->suggestPriceService->expects($this->once())->method('process')->with($result);

        ($this->handler)(new ClickerEvent('completed', 'seznam_job1', result: $result));
    }

    // --- completed: enrich_vehicle ---

    public function testCompletedEnrichVehicleDelegatesToService(): void
    {
        $result = ['action' => 'enrich_vehicle', 'articleId' => 14, 'found' => true, 'vehicleData' => ['model' => 'A4']];

        $this->enrichVehicleService->expects($this->once())->method('process')->with($result);

        ($this->handler)(new ClickerEvent('completed', 'seznam_job1', result: $result));
    }

    // --- completed: group_images ---

    public function testCompletedGroupImagesDelegatesToService(): void
    {
        $result = ['action' => 'group_images', 'batchId' => 20, 'groups' => [['category' => 'car', 'images' => ['a.jpg']]]];

        $this->groupImagesService->expects($this->once())->method('process')->with($result);

        ($this->handler)(new ClickerEvent('completed', 'batch_job1', result: $result));
    }

    // --- failed ---

    public function testFailedDelegatesToFailedService(): void
    {
        $event = new ClickerEvent('failed', 'seznam_fail1', step: 'login', error: 'Auth failed');

        $this->failedService->expects($this->once())->method('process')->with($event);

        ($this->handler)($event);
    }

    protected function setUp(): void
    {
        $this->articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $this->submissionRepository = $this->createMock(ArticleSubmissionRepositoryInterface::class);
        $this->batchRepository = $this->createMock(ImageBatchRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->groupImagesService = $this->createMock(GroupImagesCompletedService::class);
        $this->suggestPriceService = $this->createMock(SuggestPriceCompletedService::class);
        $this->enrichVehicleService = $this->createMock(EnrichVehicleCompletedService::class);
        $this->standardCompletedService = $this->createMock(ClickerStandardCompletedService::class);
        $this->failedService = $this->createMock(ClickerFailedService::class);

        $this->handler = new ClickerEventHandler(
            $this->articleRepository,
            $this->submissionRepository,
            $this->batchRepository,
            $this->logger,
            $this->groupImagesService,
            $this->suggestPriceService,
            $this->enrichVehicleService,
            $this->standardCompletedService,
            $this->failedService,
        );
    }

    private function makeArticle(int $id): Article
    {
        return ArticleMother::withId($id);
    }

    private function makeSubmission(): ArticleSubmission
    {
        return ArticleSubmissionMother::pending(Platform::Seznam);
    }

    private function makeBatch(int $id, string $jobId): ImageBatch
    {
        return ImageBatchMother::withId($id, $jobId);
    }
}
