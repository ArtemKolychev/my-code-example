<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Repository\ArticleRepositoryInterface;
use Psr\Log\LoggerInterface;

class EnrichVehicleCompletedService
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
            $this->logger->error('enrich_vehicle completed without articleId');

            return;
        }

        /** @var int|string $articleId */
        $article = $this->articleRepository->findById((int) $articleId);
        if (!$article) {
            $this->logger->error('Article not found for enrich_vehicle', ['articleId' => $articleId]);

            return;
        }

        $vehicleData = $result['vehicleData'] ?? [];
        /** @var array<string, mixed> $vehicleData */
        if (empty($vehicleData) || !(bool) ($result['found'] ?? false)) {
            $this->logger->info('enrich_vehicle: no data returned (vehicle not found or API not configured)', [
                'articleId' => $articleId,
            ]);

            return;
        }

        // Merge into article.meta, keeping existing values (do not overwrite manual entries)
        $existingMeta = $article->getMeta() ?? [];
        $merged = array_merge($vehicleData, $existingMeta);
        /** @var array<string, mixed> $merged */
        $article->setMeta($merged);
        $this->articleRepository->save($article);

        $this->logger->info('Vehicle meta enriched for article', [
            'articleId' => $articleId,
            'vehicleData' => $vehicleData,
        ]);
    }
}
