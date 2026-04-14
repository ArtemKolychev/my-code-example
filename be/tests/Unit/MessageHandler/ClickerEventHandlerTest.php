<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Article;
use App\Entity\ArticleSubmission;
use App\Entity\ImageBatch;
use App\Entity\User;
use App\Enum\Platform;
use App\Event\ArticlePublishedEvent;
use App\Message\ClickerEvent;
use App\MessageHandler\ClickerEventHandler;
use App\Repository\ArticleRepository;
use App\Repository\ArticleSubmissionRepository;
use App\Repository\ImageBatchRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ClickerEventHandlerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private ArticleRepository&MockObject $articleRepository;
    private ArticleSubmissionRepository&MockObject $submissionRepository;
    private ImageBatchRepository&MockObject $batchRepository;
    private LoggerInterface&MockObject $logger;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private ClickerEventHandler $handler;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->articleRepository = $this->createMock(ArticleRepository::class);
        $this->submissionRepository = $this->createMock(ArticleSubmissionRepository::class);
        $this->batchRepository = $this->createMock(ImageBatchRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->handler = new ClickerEventHandler(
            $this->em,
            $this->articleRepository,
            $this->submissionRepository,
            $this->batchRepository,
            $this->logger,
            $this->eventDispatcher,
        );
    }

    private function makeArticle(int $id): Article
    {
        $article = new Article();
        $article->id = $id;

        return $article;
    }

    private function makeSubmission(): ArticleSubmission
    {
        $submission = new ArticleSubmission();
        $submission->setPlatform(Platform::Seznam);

        return $submission;
    }

    private function makeBatch(int $id, string $jobId): ImageBatch
    {
        $user = $this->createMock(User::class);
        $batch = new ImageBatch();
        $batch->id = $id;
        $batch->jobId = $jobId;
        $batch->user = $user;

        return $batch;
    }

    // --- progress ---

    public function testProgressEventOnlyLogs(): void
    {
        $event = new ClickerEvent('progress', 'seznam_job1', step: 'fill_form');

        $this->logger->expects($this->atLeastOnce())->method('info');
        $this->em->expects($this->never())->method('flush');

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

        $this->articleRepository->method('find')->with(5)->willReturn($article);
        $this->submissionRepository->method('findByJobId')->willReturn($submission);
        $this->em->expects($this->exactly(2))->method('persist');
        $this->em->expects($this->once())->method('flush');

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
        $this->assertSame('processing', $submission->getStatus());
    }

    public function testNeedsInputWithArticleIdLogsErrorWhenArticleNotFound(): void
    {
        $this->articleRepository->method('find')->willReturn(null);

        $this->logger->expects($this->atLeastOnce())->method('error');
        $this->em->expects($this->never())->method('flush');

        $event = new ClickerEvent('needs_input', 'seznam_job1', articleId: 999);

        ($this->handler)($event);
    }

    // --- needs_input without articleId (batch path) ---

    public function testNeedsInputWithoutArticleIdUpdatesBatchViaSql(): void
    {
        $batch = $this->makeBatch(10, 'seznam_job2');

        $this->batchRepository->method('findByJobId')->willReturn($batch);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeStatement')->willReturn(1);
        $this->em->method('getConnection')->willReturn($connection);

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

    // --- completed: publish ---

    public function testCompletedPublishSetsIsPublishedTrueAndDispatchesEvent(): void
    {
        $article = $this->makeArticle(7);
        $submission = $this->makeSubmission();

        $this->articleRepository->method('find')->willReturn($article);
        $this->submissionRepository->method('findByJobId')->willReturn($submission);
        $this->em->expects($this->atLeastOnce())->method('persist');
        $this->em->expects($this->once())->method('flush');
        $this->eventDispatcher->expects($this->once())->method('dispatch')
            ->with($this->isInstanceOf(ArticlePublishedEvent::class));

        $event = new ClickerEvent('completed', 'seznam_job1', result: [
            'action' => 'publish',
            'articleId' => 7,
            'isOk' => true,
            'jobId' => 'seznam_job1',
        ]);

        ($this->handler)($event);

        $this->assertSame('completed', $submission->getStatus());
    }

    public function testCompletedPublishSetsSubmissionFailedOnFailure(): void
    {
        $article = $this->makeArticle(8);
        $submission = $this->makeSubmission();

        $this->articleRepository->method('find')->willReturn($article);
        $this->submissionRepository->method('findByJobId')->willReturn($submission);
        $this->em->method('persist');
        $this->em->method('flush');

        $event = new ClickerEvent('completed', 'seznam_job1', result: [
            'action' => 'publish',
            'articleId' => 8,
            'isOk' => false,
            'jobId' => 'seznam_job1',
        ]);

        ($this->handler)($event);

        $this->assertSame('failed', $submission->getStatus());
    }

    public function testCompletedPublishLogsWarningWhenNoArticleId(): void
    {
        $this->logger->expects($this->atLeastOnce())->method('warning');
        $this->articleRepository->expects($this->never())->method('find');

        $event = new ClickerEvent('completed', 'seznam_job1', result: [
            'action' => 'publish',
            // no articleId
        ]);

        ($this->handler)($event);
    }

    public function testCompletedPublishLogsErrorWhenArticleNotFound(): void
    {
        $this->articleRepository->method('find')->willReturn(null);
        $this->logger->expects($this->atLeastOnce())->method('error');

        $event = new ClickerEvent('completed', 'seznam_job1', result: [
            'action' => 'publish',
            'articleId' => 999,
        ]);

        ($this->handler)($event);
    }

    // --- completed: delete ---

    public function testCompletedDeleteClearsResultDataWhenNoActiveSubmissions(): void
    {
        $article = $this->makeArticle(11);
        $article->setPublicResultData(['url' => 'https://example.com']);

        $this->articleRepository->method('find')->willReturn($article);
        $this->submissionRepository->method('findByJobId')->willReturn(null);
        $this->submissionRepository->method('countActiveByArticle')->willReturn(0);
        $this->em->method('persist');
        $this->em->method('flush');

        $event = new ClickerEvent('completed', 'seznam_job1', result: [
            'action' => 'delete',
            'articleId' => 11,
        ]);

        ($this->handler)($event);

        $this->assertNull($article->getPublicResultData());
    }

    // --- completed: suggest_price ---

    public function testCompletedSuggestPriceSetsPrice(): void
    {
        $article = $this->makeArticle(12);

        $this->articleRepository->method('find')->willReturn($article);
        $this->em->method('persist');
        $this->em->method('flush');

        $event = new ClickerEvent('completed', 'seznam_job1', result: [
            'action' => 'suggest_price',
            'articleId' => 12,
            'price' => 4990.0,
            'reasoning' => 'Similar items sell for 5000',
            'sources' => [['name' => 'bazos', 'url' => 'https://bazos.cz/x']],
        ]);

        ($this->handler)($event);

        $this->assertSame(4990.0, $article->getPrice());
        $this->assertSame('Similar items sell for 5000', $article->getPriceReasoning());
        $this->assertCount(1, $article->getPriceSources());
    }

    public function testCompletedSuggestPriceDoesNotSetPriceWhenZero(): void
    {
        $article = $this->makeArticle(13);
        $article->setPrice(1000.0);

        $this->articleRepository->method('find')->willReturn($article);
        $this->em->method('persist');
        $this->em->method('flush');

        $event = new ClickerEvent('completed', 'seznam_job1', result: [
            'action' => 'suggest_price',
            'articleId' => 13,
            'price' => 0,
        ]);

        ($this->handler)($event);

        // price not updated when suggested price is 0
        $this->assertSame(1000.0, $article->getPrice());
    }

    // --- completed: enrich_vehicle ---

    public function testCompletedEnrichVehicleMergesDataIntoMeta(): void
    {
        $article = $this->makeArticle(14);
        $article->setMeta(['brand' => 'BMW']); // existing value should NOT be overwritten

        $this->articleRepository->method('find')->willReturn($article);
        $this->em->method('persist');
        $this->em->method('flush');

        $event = new ClickerEvent('completed', 'seznam_job1', result: [
            'action' => 'enrich_vehicle',
            'articleId' => 14,
            'found' => true,
            'vehicleData' => [
                'brand' => 'Audi',  // should NOT override existing
                'model' => 'A4',
                'year' => '2019',
            ],
        ]);

        ($this->handler)($event);

        $meta = $article->getMeta();
        $this->assertSame('BMW', $meta['brand']); // existing wins
        $this->assertSame('A4', $meta['model']);
        $this->assertSame('2019', $meta['year']);
    }

    public function testCompletedEnrichVehicleSkipsWhenNotFound(): void
    {
        $article = $this->makeArticle(15);

        $this->articleRepository->method('find')->willReturn($article);
        $this->em->expects($this->never())->method('persist');

        $event = new ClickerEvent('completed', 'seznam_job1', result: [
            'action' => 'enrich_vehicle',
            'articleId' => 15,
            'found' => false,
            'vehicleData' => [],
        ]);

        ($this->handler)($event);

        $this->assertNull($article->getMeta());
    }

    // --- completed: group_images ---

    public function testCompletedGroupImagesSetsBatchPendingInputAndStatus(): void
    {
        $batch = $this->makeBatch(20, 'batch_job1');

        $this->batchRepository->method('find')->with(20)->willReturn($batch);
        $this->em->expects($this->once())->method('flush');

        $event = new ClickerEvent('completed', 'batch_job1', result: [
            'action' => 'group_images',
            'batchId' => 20,
            'groups' => [
                [
                    'category' => 'car',
                    'title' => 'BMW M3',
                    'images' => ['a.jpg'],
                    'missing_fields' => ['brand', 'model'],
                ],
            ],
        ]);

        ($this->handler)($event);

        $this->assertSame('needs_input', $batch->getStatus());
        $this->assertIsArray($batch->pendingInput);
        $this->assertSame('group_conditions', $batch->pendingInput['inputType']);
        $this->assertCount(1, $batch->pendingInput['groups']);
        $this->assertNotEmpty($batch->pendingInput['conditionOptions']);
    }

    public function testCompletedGroupImagesSetsBatchFailedWhenEmptyGroups(): void
    {
        $batch = $this->makeBatch(21, 'batch_job2');

        $this->batchRepository->method('find')->with(21)->willReturn($batch);
        $this->em->expects($this->once())->method('flush');

        $event = new ClickerEvent('completed', 'batch_job2', result: [
            'action' => 'group_images',
            'batchId' => 21,
            'groups' => [],
        ]);

        ($this->handler)($event);

        $this->assertSame('failed', $batch->getStatus());
    }

    // --- failed ---

    public function testFailedEventSetsBatchStatusFailed(): void
    {
        $batch = $this->makeBatch(30, 'seznam_fail1');

        $this->batchRepository->method('findByJobId')->willReturn($batch);
        $this->em->expects($this->once())->method('flush');

        $event = new ClickerEvent('failed', 'seznam_fail1', step: 'login', error: 'Auth failed');

        ($this->handler)($event);

        $this->assertSame('failed', $batch->getStatus());
    }

    public function testFailedEventSetsSubmissionStatusAndErrorData(): void
    {
        $submission = $this->makeSubmission();

        $this->batchRepository->method('findByJobId')->willReturn(null);
        $this->submissionRepository->method('findByJobId')->willReturn($submission);
        $this->em->expects($this->once())->method('persist')->with($submission);
        $this->em->expects($this->once())->method('flush');

        $event = new ClickerEvent('failed', 'seznam_fail2', step: 'publish', error: 'Timeout');

        ($this->handler)($event);

        $this->assertSame('failed', $submission->getStatus());
        $this->assertSame(['step' => 'publish', 'error' => 'Timeout'], $submission->getErrorData());
    }
}
