<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Command\ClickerCommand;
use App\Application\Command\EnrichVehicleCommand;
use App\Application\Command\PublishArticleCommand;
use App\Application\DTO\Clicker\ActionInputPayload;
use App\Domain\Entity\Article;
use App\Domain\Entity\User;
use App\Domain\Enum\Condition;
use App\Domain\Enum\Platform;
use App\Domain\Repository\ArticleRepositoryInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ArticleInputService
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * Handle meta_fields input: merge fields into article meta and dispatch publish.
     *
     * @param array<string, mixed> $fields
     *
     * @return array{success: bool, error?: string}
     */
    public function handleMetaFieldsInput(Article $article, User $user, array $fields): array
    {
        if (empty($fields)) {
            return ['success' => false, 'error' => 'fields are required'];
        }

        $pendingInput = $article->getPendingInput();
        /** @var array{jobId?: string} $pendingInput */
        $jobId = $pendingInput['jobId'] ?? '';
        $platformStr = strstr($jobId, '_', true) ?: '';
        $platform = Platform::tryFrom($platformStr);

        if (!$platform) {
            return ['success' => false, 'error' => 'Cannot determine platform from jobId'];
        }

        $meta = $article->getMeta() ?? [];
        foreach ($fields as $key => $value) {
            $meta[(string) $key] = $value;
        }
        $article->setMeta($meta);
        $article->setPendingInput(null);

        $userId = $user->getId();
        $articleId = $article->getId();
        $this->articleRepository->save($article);

        if ($articleId && $userId) {
            $this->messageBus->dispatch(new PublishArticleCommand($articleId, $userId, $platform));
        }

        return ['success' => true];
    }

    /**
     * Handle category_fields input: validate and merge into article.meta.
     *
     * @param array<string, mixed> $fields
     *
     * @return array{success: bool, error?: string}
     */
    public function handleCategoryFieldsInput(Article $article, array $fields): array
    {
        if (empty($fields)) {
            return ['success' => false, 'error' => 'fields are required'];
        }

        $category = $article->getCategory();
        if (!$category) {
            return ['success' => false, 'error' => 'Article has no category set'];
        }

        if (isset($fields['condition'])) {
            /** @var string $conditionValue */
            $conditionValue = $fields['condition'];
            $error = $this->validateAndApplyCondition($article, $conditionValue);
            if ($error) {
                return ['success' => false, 'error' => $error];
            }
            unset($fields['condition']);
        }

        $meta = $article->getMeta() ?? [];
        $filtered = array_filter($fields, static fn ($v): bool => null !== $v && '' !== $v);
        $article->setMeta(array_merge($meta, $filtered));
        $article->setPendingInput(null);

        $this->articleRepository->save($article);

        return ['success' => true];
    }

    /**
     * Handle VIN or SPZ input: dispatch vehicle enrichment.
     */
    public function handleVinOrSpzInput(Article $article, int $articleId, string $code): void
    {
        $vin = preg_match('/^[A-HJ-NPR-Z0-9]{17}$/i', $code) ? strtoupper($code) : null;
        $spz = $vin ? null : $code;

        $this->messageBus->dispatch(new EnrichVehicleCommand($articleId, $vin, $spz));
        $article->setPendingInput(null);
        $this->articleRepository->save($article);
    }

    public function findArticleByIdForUser(int $id, User $user): ?Article
    {
        return $this->articleRepository->findByIdAndUser($id, $user);
    }

    /**
     * Handle SMS/code input: forward to clicker.
     */
    public function handleCodeInput(Article $article, string $jobId, string $code): void
    {
        $this->messageBus->dispatch(new ClickerCommand('action.input', new ActionInputPayload(
            jobId: $jobId,
            code: $code,
        )));

        $article->setPendingInput(null);
        $this->articleRepository->save($article);
    }

    private function validateAndApplyCondition(Article $article, string $conditionValue): ?string
    {
        $condition = Condition::tryFrom($conditionValue);
        if (!$condition) {
            return 'Invalid condition value: '.$conditionValue;
        }
        $article->setCondition($condition);

        return null;
    }
}
