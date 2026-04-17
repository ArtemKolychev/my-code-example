<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Application\Service\ClickerStandardCompletedService;
use App\Domain\Entity\Article;
use App\Domain\Entity\ArticleSubmission;
use App\Domain\Enum\Platform;
use App\Domain\Event\ArticlePublishedEvent;
use App\Domain\Event\ClickerEvent;
use App\Domain\Repository\ArticleRepositoryInterface;
use App\Domain\Repository\ArticleSubmissionRepositoryInterface;
use App\Domain\ValueObject\SubmissionStatus;
use App\Tests\Shared\Mother\ArticleMother;
use App\Tests\Shared\Mother\ArticleSubmissionMother;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ClickerStandardCompletedServiceTest extends TestCase
{
    private ArticleRepositoryInterface&MockObject $articleRepository;
    private ArticleSubmissionRepositoryInterface&MockObject $submissionRepository;
    private LoggerInterface&MockObject $logger;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private ClockInterface&MockObject $clock;
    private ClickerStandardCompletedService $service;

    // --- publish ---

    public function testPublishSetsArticleResultAndDispatchesEvent(): void
    {
        $article = $this->makeArticle(7);
        $submission = $this->makeSubmission();

        $this->articleRepository->method('findById')->with(7)->willReturn($article);
        $this->submissionRepository->method('findByJobId')->willReturn($submission);
        $this->articleRepository->expects($this->once())->method('save');
        $this->submissionRepository->expects($this->once())->method('save');
        $this->eventDispatcher->expects($this->once())->method('dispatch')
            ->with($this->isInstanceOf(ArticlePublishedEvent::class));

        $result = ['action' => 'publish', 'articleId' => 7, 'isOk' => true, 'jobId' => 'job1'];
        $event = new ClickerEvent('completed', 'job1', result: $result);

        $this->service->process($event, $result, 'publish');

        $this->assertSame(SubmissionStatus::Completed, $submission->getStatus());
    }

    public function testPublishSetsSubmissionFailedWhenIsOkFalse(): void
    {
        $article = $this->makeArticle(8);
        $submission = $this->makeSubmission();

        $this->articleRepository->method('findById')->willReturn($article);
        $this->submissionRepository->method('findByJobId')->willReturn($submission);

        $result = ['action' => 'publish', 'articleId' => 8, 'isOk' => false, 'jobId' => 'job1'];
        $this->service->process(new ClickerEvent('completed', 'job1', result: $result), $result, 'publish');

        $this->assertSame(SubmissionStatus::Failed, $submission->getStatus());
    }

    public function testPublishLogsWarningWhenNoArticleId(): void
    {
        $this->logger->expects($this->atLeastOnce())->method('warning');
        $this->articleRepository->expects($this->never())->method('findById');

        $result = ['action' => 'publish'];
        $this->service->process(new ClickerEvent('completed', 'job1', result: $result), $result, 'publish');
    }

    public function testPublishLogsErrorWhenArticleNotFound(): void
    {
        $this->articleRepository->method('findById')->willReturn(null);
        $this->logger->expects($this->atLeastOnce())->method('error');

        $result = ['action' => 'publish', 'articleId' => 999];
        $this->service->process(new ClickerEvent('completed', 'job1', result: $result), $result, 'publish');
    }

    // --- delete ---

    public function testDeleteMarksArticleWithdrawnWhenNoActiveSubmissions(): void
    {
        $article = $this->makeArticle(11);
        $article->setPublicResultData(['url' => 'https://example.com']);

        $this->articleRepository->method('findById')->willReturn($article);
        $this->submissionRepository->method('findByJobId')->willReturn(null);
        $this->submissionRepository->method('countActiveByArticle')->willReturn(0);
        $this->clock->method('now')->willReturn(new DateTimeImmutable());
        $this->articleRepository->expects($this->once())->method('save');

        $result = ['action' => 'delete', 'articleId' => 11];
        $this->service->process(new ClickerEvent('completed', 'job1', result: $result), $result, 'delete');

        $this->assertNull($article->getPublicResultData());
    }

    public function testDeleteSetsSubmissionWithdrawn(): void
    {
        $article = $this->makeArticle(12);
        $submission = ArticleSubmissionMother::completed();

        $this->articleRepository->method('findById')->willReturn($article);
        $this->submissionRepository->method('findByJobId')->willReturn($submission);
        $this->submissionRepository->method('countActiveByArticle')->willReturn(1);

        $result = ['action' => 'delete', 'articleId' => 12, 'jobId' => 'job1'];
        $this->service->process(new ClickerEvent('completed', 'job1', result: $result), $result, 'delete');

        $this->assertSame(SubmissionStatus::Withdrawn, $submission->getStatus());
    }

    protected function setUp(): void
    {
        $this->articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $this->submissionRepository = $this->createMock(ArticleSubmissionRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);

        $this->service = new ClickerStandardCompletedService(
            $this->articleRepository,
            $this->submissionRepository,
            $this->logger,
            $this->eventDispatcher,
            $this->clock,
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
}
