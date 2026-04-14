<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Article;
use App\Entity\User;
use App\Enum\Condition;
use App\Enum\Platform;
use App\Message\ClickerCommand;
use App\Message\EnrichVehicleMessage;
use App\Message\PublishMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ArticleInputService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
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
        $jobId = (string) ($pendingInput['jobId'] ?? '');
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
        $this->em->flush();

        if ($articleId && $userId) {
            $this->messageBus->dispatch(new PublishMessage($articleId, $userId, $platform));
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

        // Validate condition if provided
        if (isset($fields['condition'])) {
            $condition = Condition::tryFrom((string) $fields['condition']);
            if (!$condition) {
                return ['success' => false, 'error' => 'Invalid condition value: '.$fields['condition']];
            }
            $article->setCondition($condition);
            unset($fields['condition']);
        }

        // Merge fields into meta
        $meta = $article->getMeta() ?? [];
        foreach ($fields as $key => $value) {
            if (null !== $value && '' !== $value) {
                $meta[$key] = $value;
            }
        }
        $article->setMeta($meta);
        $article->setPendingInput(null);

        $this->em->flush();

        return ['success' => true];
    }

    /**
     * Handle VIN or SPZ input: dispatch vehicle enrichment.
     */
    public function handleVinOrSpzInput(Article $article, int $articleId, string $code): void
    {
        $vin = preg_match('/^[A-HJ-NPR-Z0-9]{17}$/i', $code) ? strtoupper($code) : null;
        $spz = $vin ? null : $code;

        $this->messageBus->dispatch(new EnrichVehicleMessage($articleId, $vin, $spz));
        $article->setPendingInput(null);
        $this->em->flush();
    }

    /**
     * Handle SMS/code input: forward to clicker.
     */
    public function handleCodeInput(Article $article, string $jobId, string $code): void
    {
        $this->messageBus->dispatch(new ClickerCommand('action.input', [
            'jobId' => $jobId,
            'code' => $code,
        ]));

        $article->setPendingInput(null);
        $this->em->flush();
    }
}
