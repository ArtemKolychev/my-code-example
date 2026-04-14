<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\Category;
use App\Enum\Condition;
use App\Event\ArticlePublishedEvent;
use App\Logging\TraceContext;
use App\Message\ClickerEvent;
use App\Registry\CategoryFieldRegistry;
use App\Repository\ArticleRepository;
use App\Repository\ArticleSubmissionRepository;
use App\Repository\ImageBatchRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler]
readonly class ClickerEventHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ArticleRepository $articleRepository,
        private ArticleSubmissionRepository $submissionRepository,
        private ImageBatchRepository $batchRepository,
        private LoggerInterface $logger,
        private EventDispatcherInterface $eventDispatcher,
    ) {
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
            'failed' => $this->handleFailed($event),
            default => $this->logger->warning('Unknown clicker event type: {type}', [
                'type' => $event->getType(),
            ]),
        };
    }

    private function handleProgress(ClickerEvent $event): void
    {
        $this->logger->info('Clicker progress: {step}', [
            'jobId' => $event->getJobId(),
            'step' => $event->getStep(),
        ]);

        $submission = $this->submissionRepository->findByJobId($event->getJobId());
        if ($submission) {
            $submission->setProgressData([
                'step' => $event->getStep() ?? '',
                'stepIndex' => $event->getStepIndex() ?? 0,
                'totalSteps' => $event->getTotalSteps() ?? 0,
                'message' => $event->getMessage() ?? '',
            ]);
            $this->entityManager->persist($submission);
            $this->entityManager->flush();
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
            // No article yet (group_images context) — save to batch by jobId
            $batch = $this->batchRepository->findByJobId($event->getJobId());
            if ($batch) {
                $this->entityManager->getConnection()->executeStatement(
                    'UPDATE image_batch SET status = :status, pending_input = :pendingInput WHERE id = :id',
                    [
                        'status' => 'needs_input',
                        'pendingInput' => json_encode($pendingInputData),
                        'id' => $batch->getId(),
                    ]
                );
                $this->logger->info('Saved needs_input to batch', ['jobId' => $event->getJobId()]);
            } else {
                $this->logger->warning('needs_input: no articleId and no batch found', [
                    'jobId' => $event->getJobId(),
                ]);
            }

            return;
        }

        $article = $this->articleRepository->find($articleId);
        if (!$article) {
            $this->logger->error('Article not found for needs_input event', [
                'articleId' => $articleId,
            ]);

            return;
        }

        $article->setPendingInput($pendingInputData);
        $this->entityManager->persist($article);

        $submission = $this->submissionRepository->findByJobId($event->getJobId());
        if ($submission) {
            $submission->setStatus('processing');
            $submission->setPendingInput($pendingInputData);
            $this->entityManager->persist($submission);
        }

        $this->entityManager->flush();
    }

    private function handleCompleted(ClickerEvent $event): void
    {
        $result = $event->getResult();
        $action = $result['action'] ?? 'publish';

        if ('group_images' === $action) {
            $this->handleGroupImagesCompleted($result);

            return;
        }

        if ('suggest_price' === $action) {
            $this->handleSuggestPriceCompleted($result);

            return;
        }

        if ('enrich_vehicle' === $action) {
            $this->handleEnrichVehicleCompleted($result);

            return;
        }

        // Original clicker actions (publish, bump, delete)
        $articleId = $result['articleId'] ?? null;

        if (null === $articleId) {
            $this->logger->warning('Completed event without articleId', [
                'jobId' => $event->getJobId(),
            ]);

            return;
        }

        $article = $this->articleRepository->find((int) $articleId);

        if (!$article) {
            $this->logger->error('Article not found for completed event', [
                'articleId' => $articleId,
            ]);

            return;
        }

        $jobId = $result['jobId'] ?? $event->getJobId();

        if ('delete' === $action) {
            if ($jobId) {
                $submission = $this->submissionRepository->findByJobId((string) $jobId);
                if ($submission) {
                    $submission->setStatus('withdrawn');
                    $submission->setResultData($result);
                    $this->entityManager->persist($submission);
                    $this->entityManager->flush();
                } else {
                    $this->logger->warning('delete completed but submission not found for jobId — countActiveByArticle may be inaccurate', [
                        'jobId' => $jobId,
                        'articleId' => $article->getId(),
                    ]);
                }
            }

            // Only mark the article as fully withdrawn when no active submissions remain
            $activeCount = $this->submissionRepository->countActiveByArticle($article);
            if (0 === $activeCount) {
                $article->setPublicResultData(null);
                $article->setWithdrawnAt(new DateTimeImmutable());
            }
        } else {
            $article->setPublicResultData($result);
            $this->eventDispatcher->dispatch(new ArticlePublishedEvent($article, $result));
        }

        $this->entityManager->persist($article);

        if ('delete' !== $action && $jobId) {
            $submission = $this->submissionRepository->findByJobId((string) $jobId);
            if ($submission) {
                $isOk = (bool) ($result['isOk'] ?? false);
                $submission->setStatus($isOk ? 'completed' : 'failed');
                $submission->setResultData($result);
                $this->entityManager->persist($submission);
            }
        }

        $this->entityManager->flush();

        $this->logger->info('Article updated after clicker completed', [
            'articleId' => $article->getId(),
            'action' => $action,
        ]);
    }

    private function handleGroupImagesCompleted(array $result): void
    {
        $batchId = $result['batchId'] ?? null;
        if (null === $batchId) {
            $this->logger->error('group_images completed without batchId');

            return;
        }

        $batch = $this->batchRepository->find((int) $batchId);
        if (!$batch) {
            $this->logger->error('ImageBatch not found', ['batchId' => $batchId]);

            return;
        }

        $groups = $result['groups'] ?? [];
        if (empty($groups) || !is_array($groups)) {
            $batch->setStatus('failed');
            $this->entityManager->flush();
            $this->logger->warning('group_images returned empty groups', ['batchId' => $batchId]);

            return;
        }

        // Ask user to confirm condition per group before creating articles
        $conditionOptions = array_map(
            static fn (Condition $c) => ['value' => $c->value, 'label' => $c->label()],
            Condition::cases()
        );
        $indexedGroups = array_map(
            static fn (int $i, array $g) => array_merge($g, ['index' => $i]),
            array_keys($groups),
            $groups
        );

        // Annotate each group with missing required fields and optional fields.
        // Use the LLM's missing_fields directly — it already knows what it couldn't determine.
        foreach ($indexedGroups as &$group) {
            $category = Category::tryFrom($group['category'] ?? '');
            $llmMissingFields = $group['missing_fields'] ?? [];

            $missingRequired = [];
            $optionalFields = [];

            if ($category && !empty($llmMissingFields)) {
                $fieldDefsByKey = [];
                foreach (CategoryFieldRegistry::getFields($category) as $field) {
                    $fieldDefsByKey[$field['key']] = $field;
                }

                foreach ($llmMissingFields as $fieldKey) {
                    if ('condition' === $fieldKey) {
                        continue; // condition handled separately via select
                    }
                    if (!isset($fieldDefsByKey[$fieldKey])) {
                        continue; // no UI definition for this field
                    }
                    $field = $fieldDefsByKey[$fieldKey];
                    if ($field['required']) {
                        $missingRequired[] = $field;
                    } else {
                        $optionalFields[] = $field;
                    }
                }
            }

            $group['missingRequiredFields'] = $missingRequired;
            $group['optionalFields'] = $optionalFields;
        }
        unset($group);

        $batch->pendingInput = [
            'inputType' => 'group_conditions',
            'groups' => $indexedGroups,
            'conditionOptions' => $conditionOptions,
        ];
        $batch->setStatus('needs_input');

        // Deduct tokens from user
        $tokensUsed = isset($result['tokensUsed']) ? (int) $result['tokensUsed'] : 0;
        if ($tokensUsed > 0) {
            $user = $batch->getUser();
            $user->deductTokens($tokensUsed);
            $this->entityManager->persist($user);
            $this->logger->info('Deducted {tokens} tokens from user {userId} (group_images)', [
                'tokens' => $tokensUsed,
                'userId' => $user->getId(),
                'remaining' => $user->getTokenBalance(),
            ]);
        }

        $this->entityManager->flush();

        $this->logger->info('group_images grouped, waiting for per-group condition input', [
            'batchId' => $batchId,
            'groupCount' => count($indexedGroups),
        ]);
    }

    private function handleSuggestPriceCompleted(array $result): void
    {
        $articleId = $result['articleId'] ?? null;
        if (null === $articleId) {
            $this->logger->error('suggest_price completed without articleId');

            return;
        }

        $article = $this->articleRepository->find((int) $articleId);
        if (!$article) {
            $this->logger->error('Article not found for suggest_price', ['articleId' => $articleId]);

            return;
        }

        $price = (float) ($result['price'] ?? 0);
        $reasoning = isset($result['reasoning']) ? (string) $result['reasoning'] : null;
        $sources = isset($result['sources']) && is_array($result['sources']) ? $result['sources'] : null;

        if ($price > 0) {
            $article->setPrice($price);
        }
        $article->setPriceReasoning($reasoning);
        $article->setPriceSources($sources);
        $this->entityManager->persist($article);

        // Deduct tokens from user
        $tokensUsed = isset($result['tokensUsed']) ? (int) $result['tokensUsed'] : 0;
        if ($tokensUsed > 0) {
            $user = $article->getUser();
            if ($user) {
                $user->deductTokens($tokensUsed);
                $this->entityManager->persist($user);
                $this->logger->info('Deducted {tokens} tokens from user {userId} (suggest_price)', [
                    'tokens' => $tokensUsed,
                    'userId' => $user->getId(),
                    'remaining' => $user->getTokenBalance(),
                ]);
            }
        }

        $this->entityManager->flush();

        $this->logger->info('Price suggested for article', [
            'articleId' => $articleId,
            'price' => $price,
            'reasoning' => $result['reasoning'] ?? '',
        ]);
    }

    private function handleEnrichVehicleCompleted(array $result): void
    {
        $articleId = $result['articleId'] ?? null;
        if (null === $articleId) {
            $this->logger->error('enrich_vehicle completed without articleId');

            return;
        }

        $article = $this->articleRepository->find((int) $articleId);
        if (!$article) {
            $this->logger->error('Article not found for enrich_vehicle', ['articleId' => $articleId]);

            return;
        }

        $vehicleData = $result['vehicleData'] ?? [];
        if (empty($vehicleData) || !(bool) ($result['found'] ?? false)) {
            $this->logger->info('enrich_vehicle: no data returned (vehicle not found or API not configured)', [
                'articleId' => $articleId,
            ]);

            return;
        }

        // Merge into article.meta, keeping existing values (do not overwrite manual entries)
        $existingMeta = $article->getMeta() ?? [];
        $merged = array_merge($vehicleData, $existingMeta); // existing takes priority
        $article->setMeta($merged);
        $this->entityManager->persist($article);
        $this->entityManager->flush();

        $this->logger->info('Vehicle meta enriched for article', [
            'articleId' => $articleId,
            'vehicleData' => $vehicleData,
        ]);
    }

    private function handleFailed(ClickerEvent $event): void
    {
        $this->logger->error('Clicker job failed: {error}', [
            'jobId' => $event->getJobId(),
            'step' => $event->getStep(),
            'error' => $event->getError(),
        ]);

        // Check if it's a batch failure
        $batch = $this->batchRepository->findByJobId($event->getJobId());
        if ($batch) {
            $batch->setStatus('failed');
            $this->entityManager->flush();

            return;
        }

        $submission = $this->submissionRepository->findByJobId($event->getJobId());
        if ($submission) {
            // If delete job failed because article was not found on platform — treat as success
            $isDeleteNotFound = 'seznam-delete' === $event->getStep()
                && $this->isNotFoundError($event->getError() ?? '');

            if ($isDeleteNotFound) {
                $this->logger->info('Delete failed with not-found error — treating as withdrawn', [
                    'jobId' => $event->getJobId(),
                ]);

                $submission->setStatus('withdrawn');
                $submission->setResultData(['deleted' => true, 'notFound' => true]);
                $this->entityManager->persist($submission);
                $this->entityManager->flush();

                $article = $submission->getArticle();
                if ($article) {
                    $activeCount = $this->submissionRepository->countActiveByArticle($article);
                    if (0 === $activeCount) {
                        $article->setPublicResultData(null);
                        $article->setWithdrawnAt(new DateTimeImmutable());
                        $this->entityManager->persist($article);
                    }
                }
            } elseif ('deleting' === $submission->getStatus()) {
                // Delete job failed — article is still live on the platform, restore to completed
                $this->logger->warning('Delete job failed, restoring submission to completed', [
                    'jobId' => $event->getJobId(),
                    'step' => $event->getStep(),
                    'error' => $event->getError(),
                ]);
                $submission->setStatus('completed');
                $submission->setErrorData([
                    'step' => $event->getStep(),
                    'error' => $event->getError(),
                ]);
            } else {
                $submission->setStatus('failed');
                $submission->setErrorData([
                    'step' => $event->getStep(),
                    'error' => $event->getError(),
                ]);
            }

            $this->entityManager->persist($submission);
            $this->entityManager->flush();
        }
    }

    private function isNotFoundError(string $error): bool
    {
        $lowerError = strtolower($error);
        $notFoundPatterns = ['404', 'not found', 'notfound', 'inzerát neexistuje', 'neexistuje'];
        foreach ($notFoundPatterns as $pattern) {
            if (str_contains($lowerError, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
