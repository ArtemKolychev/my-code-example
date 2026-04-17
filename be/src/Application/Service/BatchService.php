<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Command\GroupImagesCommand;
use App\Application\Command\SuggestPriceCommand;
use App\Application\DTO\Request\BatchInputRequest;
use App\Application\DTO\Response\BatchOperationResult;
use App\Application\DTO\Response\BatchReadModel;
use App\Domain\Entity\Article;
use App\Domain\Entity\ImageBatch;
use App\Domain\Entity\User;
use App\Domain\Repository\ImageBatchRepositoryInterface;
use App\Domain\ValueObject\BatchStatus;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class BatchService
{
    public function __construct(
        private ImageBatchRepositoryInterface $imageBatchRepository,
        private MessageBusInterface $messageBus,
        private ArticleFactory $articleFactory,
    ) {
    }

    public function retryBatch(BatchReadModel $batchReadModel): BatchOperationResult
    {
        $batch = $this->imageBatchRepository->findById($batchReadModel->id);
        if (!$batch) {
            return new BatchOperationResult(success: false, error: 'Batch not found');
        }

        if (BatchStatus::Failed !== $batch->getStatus()) {
            return new BatchOperationResult(success: false, error: 'Batch is not in failed state');
        }

        $batch->startProcessing(uniqid('group_', true));
        $this->imageBatchRepository->save($batch);

        $this->messageBus->dispatch(new GroupImagesCommand((int) $batch->getId()));

        return new BatchOperationResult(success: true);
    }

    public function handleInput(BatchReadModel $batchReadModel, BatchInputRequest $request): BatchOperationResult
    {
        $batch = $this->imageBatchRepository->findById($batchReadModel->id);
        if (!$batch) {
            return new BatchOperationResult(success: false, error: 'Batch not found');
        }

        if (BatchStatus::NeedsInput !== $batch->getStatus()) {
            return new BatchOperationResult(success: false, error: 'Batch is not waiting for input');
        }

        if ('group_conditions' === $request->inputType) {
            return $this->handleGroupConditionsInput($batch, $request);
        }

        return $this->handleCodeInput($batch, $request);
    }

    private function handleGroupConditionsInput(ImageBatch $batch, BatchInputRequest $request): BatchOperationResult
    {
        /** @var array<array{index?: int, group_name: string, description: string, images: array<string>, category?: string, condition?: string, extracted_fields?: array<string, mixed>, vehicleData?: array<string, mixed>}> $groups */
        $groups = $batch->pendingInput['groups'] ?? [];
        foreach ($groups as &$group) {
            $this->applyGroupInput($group, $request);
        }
        unset($group);

        /** @var User $user */
        $user = $batch->getUser();
        $articles = $this->articleFactory->createFromGroups($groups, $user);
        $articleIds = array_values(array_filter(array_map(fn (Article $a): ?int => $a->getId(), $articles)));

        $batch->complete($articleIds);
        $this->imageBatchRepository->save($batch);

        foreach ($articles as $article) {
            $id = $article->getId();
            if ($id) {
                $this->messageBus->dispatch(new SuggestPriceCommand($id));
            }
        }

        return new BatchOperationResult(success: true);
    }

    /**
     * @param array{index?: int, condition?: string, extracted_fields?: array<string, mixed>} $group
     */
    private function applyGroupInput(array &$group, BatchInputRequest $request): void
    {
        $idx = $group['index'] ?? null;
        if (null !== $idx && isset($request->groupConditions[(int) $idx])) {
            $group['condition'] = $request->groupConditions[(int) $idx];
        }
        if (null !== $idx && isset($request->groupFields[(int) $idx])) {
            $group['extracted_fields'] = array_merge(
                $group['extracted_fields'] ?? [],
                array_filter($request->groupFields[(int) $idx], static fn ($v): bool => '' !== $v && null !== $v),
            );
        }
    }

    private function handleCodeInput(ImageBatch $batch, BatchInputRequest $request): BatchOperationResult
    {
        $batch->startProcessing($batch->getJobId());
        $this->imageBatchRepository->save($batch);

        $this->messageBus->dispatch(new GroupImagesCommand((int) $batch->getId(), $request->code));

        return new BatchOperationResult(success: true);
    }
}
