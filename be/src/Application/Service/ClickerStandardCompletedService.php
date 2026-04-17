<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Entity\Article;
use App\Domain\Entity\ArticleSubmission;
use App\Domain\Event\ArticlePublishedEvent;
use App\Domain\Event\ClickerEvent;
use App\Domain\Repository\ArticleRepositoryInterface;
use App\Domain\Repository\ArticleSubmissionRepositoryInterface;
use App\Domain\ValueObject\SubmissionStatus;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ClickerStandardCompletedService
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly ArticleSubmissionRepositoryInterface $articleSubmissionRepository,
        private readonly LoggerInterface $logger,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ClockInterface $clock,
    ) {
    }

    /** @param array<string, mixed> $result */
    public function process(ClickerEvent $event, array $result, string $action): void
    {
        $articleId = $result['articleId'] ?? null;

        if (null === $articleId) {
            $this->logger->warning('Completed event without articleId', ['jobId' => $event->getJobId()]);

            return;
        }

        /** @var int|string $articleId */
        $article = $this->articleRepository->findById((int) $articleId);

        if (!$article) {
            $this->logger->error('Article not found for completed event', ['articleId' => $articleId]);

            return;
        }

        $jobId = $result['jobId'] ?? $event->getJobId();
        /** @var string|null $jobId */
        $jobIdStr = null !== $jobId ? (string) $jobId : null;

        match ('delete' === $action) {
            true => $this->handleDeleteAction($article, $result, $jobIdStr),
            false => $this->handlePublishAction($article, $result, $jobIdStr),
        };

        $this->logger->info('Article updated after clicker completed', [
            'articleId' => $article->getId(),
            'action' => $action,
        ]);
    }

    /** @param array<string, mixed> $result */
    private function handleDeleteAction(Article $article, array $result, ?string $jobId): void
    {
        if ($jobId) {
            $submission = $this->articleSubmissionRepository->findByJobId($jobId);
            match (null !== $submission) {
                true => $this->saveWithdrawnSubmission($submission, $result),
                false => $this->logger->warning('delete completed but submission not found for jobId — countActiveByArticle may be inaccurate', [
                    'jobId' => $jobId,
                    'articleId' => $article->getId(),
                ]),
            };
        }

        $activeCount = $this->articleSubmissionRepository->countActiveByArticle($article);
        if (0 === $activeCount) {
            $article->markWithdrawn($this->clock->now());
            $this->articleRepository->save($article);
        }
    }

    /** @param array<string, mixed> $result */
    private function saveWithdrawnSubmission(ArticleSubmission $submission, array $result): void
    {
        $submission->setStatus(SubmissionStatus::Withdrawn);
        $submission->setResultData($result);
        $this->articleSubmissionRepository->save($submission);
    }

    /** @param array<string, mixed> $result */
    private function handlePublishAction(Article $article, array $result, ?string $jobId): void
    {
        $article->setPublicResultData($result);
        $this->articleRepository->save($article);
        $this->eventDispatcher->dispatch(new ArticlePublishedEvent($article, $result));

        if (!$jobId) {
            return;
        }

        $submission = $this->articleSubmissionRepository->findByJobId($jobId);
        if (!$submission) {
            return;
        }

        $isOk = (bool) ($result['isOk'] ?? false);
        $submission->setStatus($isOk ? SubmissionStatus::Completed : SubmissionStatus::Failed);
        $submission->setResultData($result);
        $this->articleSubmissionRepository->save($submission);
    }
}
