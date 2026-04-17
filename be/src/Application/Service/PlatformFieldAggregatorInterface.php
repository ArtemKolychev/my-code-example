<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Entity\Article;
use App\Domain\Enum\Platform;

interface PlatformFieldAggregatorInterface
{
    /**
     * Returns missing field names for a specific platform.
     *
     * @return string[]
     */
    public function getMissingFieldsForPlatform(Article $article, Platform $platform): array;

    /**
     * Returns deduplicated missing fields across ALL platforms and category requirements.
     *
     * @return array<string, array{label: string, type: string, platforms: Platform[]}>
     */
    public function getMissingFields(Article $article): array;

    /**
     * @return string[]
     */
    public function getCategoryMissingFields(Article $article): array;

    /** @return array<string, array{label: string, type: string}> */
    public function getAllFieldDefinitions(): array;
}
