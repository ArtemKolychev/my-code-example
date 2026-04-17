<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Logging\TraceContext;
use App\Application\Service\ClickerFailedService;
use App\Application\Service\ClickerStandardCompletedService;
use App\Application\Service\EnrichVehicleCompletedService;
use App\Application\Service\GroupImagesCompletedService;
use App\Application\Service\SuggestPriceCompletedService;
use App\Domain\Entity\Article;
use App\Domain\Event\ClickerEvent;
use App\Domain\Repository\ArticleRepositoryInterface;
use App\Domain\Repository\ArticleSubmissionRepositoryInterface;
use App\Domain\Repository\ImageBatchRepositoryInterface;
use App\Domain\ValueObject\SubmissionStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: ClickerEvent::class)]
final readonly class ClickerEventHandler
{
    public function __construct(
        private ArticleRepositoryInterface $articleRepository,
        private ArticleSubmissionRepositoryInterface $articleSubmissionRepository,
        private ImageBatchRepositoryInterface $imageBatchRepository,
        private LoggerInterface $logger,
        private GroupImagesCompletedService $groupImagesService,
        private SuggestPriceCompletedService $suggestPriceService,
        private EnrichVehicleCompletedService $enrichVehicleService,
        private ClickerStandardCompletedService $standardCompletedService,
        private ClickerFailedService $failedService,
    ) {
    }

    private function handleProgress(ClickerEvent $event): void
    {
        $this->logger->info('Clicker progress: {step}', [
            'jobId' => $event->getJobId(),
            'step' => $event->getStep(),
        ]);

        $submission = $this->articleSubmissionRepository->findByJobId($event->getJobId());
        if ($submission) {
            $submission->setProgressData([
                'step' => $event->getStep() ?? '',
                'stepIndex' => $event->getStepIndex() ?? 0,
                'totalSteps' => $event->getTotalSteps() ?? 0,
                'message' => $event->getMessage() ?? '',
            ]);
            $this->articleSubmissionRepository->save($submission);
        }
    }

    private function handleNeedsInput(ClickerEvent $event): void
    {
        $this->logger->info('Clicker needs input: {inputType}', [
            'jobId' => $event->getJobId(),
            'inputType' => $event->getInputType(),
            'inputPrompt' => $event->getInputPrompt(),
            'articleId' => $event->getArticleId(),
            'fields' => $event->getFields(),
        ]);

        $pendingInputData = [
            'jobId' => $event->getJobId(),
            'inputType' => $event->getInputType(),
            'inputPrompt' => $event->getInputPrompt(),
            'imageUrls' => $event->getImageUrls() ?? [],
            'fields' => $event->getFields() ?? [],
        ];

        $articleId = $event->getArticleId();

        if (null === $articleId) {
            $this->saveNeedsInputToBatch($event, $pendingInputData);

            return;
        }

        $article = $this->articleRepository->findById($articleId);
        if (!$article) {
            $this->logger->error('Article not found for needs_input event', ['articleId' => $articleId]);

            return;
        }

        $this->saveNeedsInputToArticle($event, $article, $pendingInputData);
    }

    /** @param array<string, mixed> $pendingInputData */
    private function saveNeedsInputToBatch(ClickerEvent $event, array $pendingInputData): void
    {
        // No article yet (group_images context) — save to batch by jobId
        $batch = $this->imageBatchRepository->findByJobId($event->getJobId());
        if (!$batch) {
            $this->logger->warning('needs_input: no articleId and no batch found', [
                'jobId' => $event->getJobId(),
            ]);

            return;
        }

        $batchId = $batch->getId();
        if (null !== $batchId) {
            $this->imageBatchRepository->markNeedsInput($batchId, $pendingInputData);
        }
        $this->logger->info('Saved needs_input to batch', ['jobId' => $event->getJobId()]);
    }

    /** @param array<string, mixed> $pendingInputData */
    private function saveNeedsInputToArticle(ClickerEvent $event, Article $article, array $pendingInputData): void
    {
        $article->setPendingInput($pendingInputData);

        $submission = $this->articleSubmissionRepository->findByJobId($event->getJobId());
        if ($submission) {
            $submission->setStatus(SubmissionStatus::Processing);
            $submission->setPendingInput($pendingInputData);
            $this->articleSubmissionRepository->save($submission);
        }

        $this->articleRepository->save($article);
    }

    private function handleCompleted(ClickerEvent $event): void
    {
        $result = $event->getResult();
        /** @var string $action */
        $action = $result['action'] ?? 'publish';

        match ($action) {
            'group_images' => $this->groupImagesService->process($result),
            'suggest_price' => $this->suggestPriceService->process($result),
            'enrich_vehicle' => $this->enrichVehicleService->process($result),
            default => $this->standardCompletedService->process($event, $result, $action),
        };
    }

    public function __invoke(ClickerEvent $event): void
    {
        TraceContext::setJobId($event->getJobId());

        $this->logger->info('Received clicker event', [
            'type' => $event->getType(),
            'jobId' => $event->getJobId(),
        ]);

        match ($event->getType()) {
            'progress' => $this->handleProgress($event),
            'needs_input' => $this->handleNeedsInput($event),
            'completed' => $this->handleCompleted($event),
            'failed' => $this->failedService->process($event),
            default => $this->logger->warning('Unknown clicker event type: {type}', [
                'type' => $event->getType(),
            ]),
        };
    }
}
