<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Entity\Article;
use App\Domain\Entity\ArticleSubmission;
use App\Domain\Event\ClickerEvent;
use App\Domain\Repository\ArticleRepositoryInterface;
use App\Domain\Repository\ArticleSubmissionRepositoryInterface;
use App\Domain\Repository\ImageBatchRepositoryInterface;
use App\Domain\ValueObject\BatchStatus;
use App\Domain\ValueObject\SubmissionStatus;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

class ClickerFailedService
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly ArticleSubmissionRepositoryInterface $articleSubmissionRepository,
        private readonly ImageBatchRepositoryInterface $imageBatchRepository,
        private readonly LoggerInterface $logger,
        private readonly ClockInterface $clock,
    ) {
    }

    public function process(ClickerEvent $event): void
    {
        $this->logger->error('Clicker job failed: {error}', [
            'jobId' => $event->getJobId(),
            'step' => $event->getStep(),
            'error' => $event->getError(),
        ]);

        $batch = $this->imageBatchRepository->findByJobId($event->getJobId());
        if ($batch) {
            $batch->setStatus(BatchStatus::Failed);
            $this->imageBatchRepository->save($batch);

            return;
        }

        $submission = $this->articleSubmissionRepository->findByJobId($event->getJobId());
        if ($submission) {
            $this->handleFailedSubmission($submission, $event);
        }
    }

    private function handleFailedSubmission(ArticleSubmission $submission, ClickerEvent $event): void
    {
        $isDeleteNotFound = 'seznam-delete' === $event->getStep()
            && $this->isNotFoundError($event->getError() ?? '');

        match (true) {
            $isDeleteNotFound => $this->processDeleteNotFound($submission, $event),
            SubmissionStatus::Deleting === $submission->getStatus() => $this->restoreDeletedSubmission($submission, $event),
            default => $this->failSubmission($submission, $event),
        };
    }

    private function processDeleteNotFound(ArticleSubmission $submission, ClickerEvent $event): void
    {
        $this->logger->info('Delete failed with not-found error — treating as withdrawn', [
            'jobId' => $event->getJobId(),
        ]);
        $submission->setStatus(SubmissionStatus::Withdrawn);
        $submission->setResultData(['deleted' => true, 'notFound' => true]);
        $this->articleSubmissionRepository->save($submission);
        $this->markArticleWithdrawnIfNoActive($submission->getArticle());
    }

    private function restoreDeletedSubmission(ArticleSubmission $submission, ClickerEvent $event): void
    {
        $this->logger->warning('Delete job failed, restoring submission to completed', [
            'jobId' => $event->getJobId(),
            'step' => $event->getStep(),
            'error' => $event->getError(),
        ]);
        $submission->setStatus(SubmissionStatus::Completed);
        $submission->setErrorData([
            'step' => $event->getStep(),
            'error' => $event->getError(),
        ]);
        $this->articleSubmissionRepository->save($submission);
    }

    private function failSubmission(ArticleSubmission $submission, ClickerEvent $event): void
    {
        $submission->setStatus(SubmissionStatus::Failed);
        $submission->setErrorData([
            'step' => $event->getStep(),
            'error' => $event->getError(),
        ]);
        $this->articleSubmissionRepository->save($submission);
    }

    private function markArticleWithdrawnIfNoActive(?Article $article): void
    {
        if (!$article) {
            return;
        }

        $activeCount = $this->articleSubmissionRepository->countActiveByArticle($article);
        if (0 === $activeCount) {
            $article->markWithdrawn($this->clock->now());
            $this->articleRepository->save($article);
        }
    }

    private function isNotFoundError(string $error): bool
    {
        $lowerError = strtolower($error);

        return (bool) array_filter(
            ['404', 'not found', 'notfound', 'inzerát neexistuje', 'neexistuje'],
            static fn (string $pattern): bool => str_contains($lowerError, $pattern)
        );
    }
}
