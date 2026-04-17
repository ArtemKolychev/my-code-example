<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Entity\User;
use App\Domain\Enum\Category;
use App\Domain\Enum\Condition;
use App\Domain\Registry\CategoryFieldRegistry;
use App\Domain\Repository\ImageBatchRepositoryInterface;
use App\Domain\ValueObject\BatchStatus;
use Psr\Log\LoggerInterface;

class GroupImagesCompletedService
{
    public function __construct(
        private readonly ImageBatchRepositoryInterface $imageBatchRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @param array<string, mixed> $result */
    public function process(array $result): void
    {
        $batchId = $result['batchId'] ?? null;
        if (null === $batchId) {
            $this->logger->error('group_images completed without batchId');

            return;
        }

        /** @var int|string $batchId */
        $batch = $this->imageBatchRepository->findById((int) $batchId);
        if (!$batch) {
            $this->logger->error('ImageBatch not found', ['batchId' => $batchId]);

            return;
        }

        $groups = $result['groups'] ?? [];
        if (empty($groups) || !is_array($groups)) {
            $batch->setStatus(BatchStatus::Failed);
            $this->imageBatchRepository->save($batch);
            $this->logger->warning('group_images returned empty groups', ['batchId' => $batchId]);

            return;
        }

        $conditionOptions = array_map(
            static fn (Condition $c): array => ['value' => $c->value, 'label' => $c->label()],
            Condition::cases()
        );
        $indexedGroups = array_map(
            static fn (int|string $i, mixed $g): array => array_merge(is_array($g) ? $g : [], ['index' => $i]),
            array_keys($groups),
            $groups
        );

        foreach ($indexedGroups as &$group) {
            /** @var array<string, mixed> $group */
            $this->annotateGroupFields($group);
        }
        unset($group);

        $batch->pendingInput = [
            'inputType' => 'group_conditions',
            'groups' => $indexedGroups,
            'conditionOptions' => $conditionOptions,
        ];
        $batch->setStatus(BatchStatus::NeedsInput);

        /** @var int|float|string $tokensUsedRaw */
        $tokensUsedRaw = $result['tokensUsed'] ?? 0;
        $tokensUsed = (int) $tokensUsedRaw;
        $this->deductTokensIfUsed($batch->getUser(), $tokensUsed);

        $this->imageBatchRepository->save($batch);

        $this->logger->info('group_images grouped, waiting for per-group condition input', [
            'batchId' => $batchId,
            'groupCount' => count($indexedGroups),
        ]);
    }

    /** @param array<string, mixed> $group */
    private function annotateGroupFields(array &$group): void
    {
        /** @var string $categoryRaw */
        $categoryRaw = $group['category'] ?? '';
        $category = Category::tryFrom($categoryRaw);
        $llmMissingFields = $group['missing_fields'] ?? [];
        /** @var array<string> $llmMissingFields */
        $group['missingRequiredFields'] = [];
        $group['optionalFields'] = [];

        if (!$category || empty($llmMissingFields)) {
            return;
        }

        [$group['missingRequiredFields'], $group['optionalFields']] = $this->fillGroupAnnotations($category, $llmMissingFields);
    }

    /**
     * @param array<string> $llmMissingFields
     *
     * @return array{array<mixed>, array<mixed>}
     */
    private function fillGroupAnnotations(Category $category, array $llmMissingFields): array
    {
        $fieldDefsByKey = $this->buildFieldDefsByKey($category);
        $missingRequired = [];
        $optionalFields = [];

        foreach ($llmMissingFields as $fieldKey) {
            if ('condition' === $fieldKey) {
                continue;
            }
            if (!isset($fieldDefsByKey[$fieldKey])) {
                continue;
            }
            /** @var array<string, mixed> $field */
            $field = $fieldDefsByKey[$fieldKey];
            match ((bool) $field['required']) {
                true => $missingRequired[] = $field,
                false => $optionalFields[] = $field,
            };
        }

        return [$missingRequired, $optionalFields];
    }

    /** @return array<string, mixed> */
    private function buildFieldDefsByKey(Category $category): array
    {
        $fieldDefsByKey = [];
        foreach (CategoryFieldRegistry::getFields($category) as $field) {
            $fieldDefsByKey[$field['key']] = $field;
        }

        return $fieldDefsByKey;
    }

    private function deductTokensIfUsed(User $user, int $tokensUsed): void
    {
        if ($tokensUsed > 0) {
            $user->deductTokens($tokensUsed);
            $this->logger->info('Deducted {tokens} tokens from user {userId} (group_images)', [
                'tokens' => $tokensUsed,
                'userId' => $user->getId(),
                'remaining' => $user->getTokenBalance(),
            ]);
        }
    }
}
