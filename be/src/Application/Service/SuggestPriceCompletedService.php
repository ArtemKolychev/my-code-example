<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\ArticleRepositoryInterface;
use Psr\Log\LoggerInterface;

class SuggestPriceCompletedService
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @param array<string, mixed> $result */
    public function process(array $result): void
    {
        $articleId = $result['articleId'] ?? null;
        if (null === $articleId) {
            $this->logger->error('suggest_price completed without articleId');

            return;
        }

        /** @var int|string $articleId */
        $article = $this->articleRepository->findById((int) $articleId);
        if (!$article) {
            $this->logger->error('Article not found for suggest_price', ['articleId' => $articleId]);

            return;
        }

        /** @var int|float|string $priceRaw */
        $priceRaw = $result['price'] ?? 0;
        $price = (float) $priceRaw;
        /** @var string|null $reasoningRaw */
        $reasoningRaw = $result['reasoning'] ?? null;
        $sources = isset($result['sources']) && is_array($result['sources']) ? $result['sources'] : null;
        /** @var array<int, array{name: string, url: string}>|null $sources */
        if ($price > 0) {
            $article->setPrice($price);
        }
        $article->setPriceReasoning($reasoningRaw);
        $article->setPriceSources($sources);

        /** @var int|float|string $tokensUsedRaw */
        $tokensUsedRaw = $result['tokensUsed'] ?? 0;
        $tokensUsed = (int) $tokensUsedRaw;
        $this->deductTokensIfUsed($article->getUser(), $tokensUsed);

        $this->articleRepository->save($article);

        $this->logger->info('Price suggested for article', [
            'articleId' => $articleId,
            'price' => $price,
            'reasoning' => $result['reasoning'] ?? '',
        ]);
    }

    private function deductTokensIfUsed(?User $user, int $tokensUsed): void
    {
        if (!$user || $tokensUsed <= 0) {
            return;
        }

        $user->deductTokens($tokensUsed);
        $this->logger->info('Deducted {tokens} tokens from user {userId} (suggest_price)', [
            'tokens' => $tokensUsed,
            'userId' => $user->getId(),
            'remaining' => $user->getTokenBalance(),
        ]);
    }
}
