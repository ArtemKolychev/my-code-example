<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter;

use App\Application\Service\PlatformFieldAggregatorInterface;
use App\Domain\Entity\Article;
use App\Domain\Enum\Platform;
use App\Domain\Port\PlatformFieldProviderInterface;

class PlatformFieldAggregator implements PlatformFieldAggregatorInterface
{
    /** @var array<string, string[]> */
    private const array CATEGORY_REQUIRED_FIELDS = [
        'car' => ['brand', 'model', 'year', 'condition'],
        'truck' => ['brand', 'model', 'year', 'condition'],
        'motorcycle' => ['brand', 'model', 'year', 'condition'],
        'electronics' => ['brand', 'condition'],
        'mobile_phone' => ['brand', 'model', 'condition'],
        'clothing' => ['size', 'condition'],
        'home_garden' => ['condition'],
        'children_goods' => ['item_type', 'condition'],
        'sport' => ['item_type', 'condition'],
        'books_media' => ['condition'],
        'photo_video' => ['brand', 'condition'],
        'computer' => ['brand', 'condition'],
        'moto_parts' => ['condition'],
        'tools' => ['condition'],
        'other' => ['condition'],
    ];

    /** @param iterable<PlatformFieldProviderInterface> $providers */
    public function __construct(private readonly iterable $providers)
    {
    }

    /**
     * Returns deduplicated missing fields across ALL platforms and category requirements.
     *
     * @return array<string, array{label: string, type: string, platforms: Platform[]}>
     */
    public function getMissingFields(Article $article): array
    {
        $meta = $article->getMeta() ?? [];
        $merged = [];

        foreach ($this->providers as $provider) {
            $this->mergeProviderFields($merged, $provider, $meta);
        }

        // Include category-based missing fields
        $categoryMissing = $this->getCategoryMissingFields($article);
        foreach ($categoryMissing as $fieldKey) {
            if (!isset($merged[$fieldKey])) {
                $merged[$fieldKey] = [
                    'label' => $fieldKey,
                    'type' => 'text',
                    'platforms' => [],
                ];
            }
        }

        return $merged;
    }

    /**
     * Returns missing field names for a specific platform.
     *
     * @return string[]
     */
    public function getMissingFieldsForPlatform(Article $article, Platform $platform): array
    {
        $meta = $article->getMeta() ?? [];

        foreach ($this->providers as $provider) {
            if ($provider->getPlatform() !== $platform) {
                continue;
            }
            $missing = [];
            foreach ($provider->getRequiredMetaFields() as $fieldName => $def) {
                if (empty($meta[$fieldName])) {
                    $missing[] = $fieldName;
                }
            }

            return $missing;
        }

        return [];
    }

    /**
     * Category-specific required fields (mirrors TypeScript CATEGORY_FIELD_REGISTRY).
     * Returns field keys that are required for the article's category but missing from meta.
     *
     * @return string[]
     */
    public function getCategoryMissingFields(Article $article): array
    {
        $category = $article->getCategory();
        if (!$category) {
            return [];
        }

        $requiredByCategory = self::CATEGORY_REQUIRED_FIELDS[$category->value];
        $meta = $article->getMeta() ?? [];

        $missing = [];
        foreach ($requiredByCategory as $fieldKey) {
            if (empty($meta[$fieldKey]) && 'condition' !== $fieldKey) {
                // condition is stored on the entity, not in meta
                $missing[] = $fieldKey;
            }
        }

        // Also check condition on the entity itself
        if (null === $article->getCondition()) {
            $missing[] = 'condition';
        }

        return $missing;
    }

    /** @return array<string, array{label: string, type: string}> */
    public function getAllFieldDefinitions(): array
    {
        $all = [];
        foreach ($this->providers as $provider) {
            $all += $provider->getRequiredMetaFields();
        }

        return $all;
    }

    /**
     * Merges required fields from a single provider into the $merged map,
     * skipping fields already filled in $meta.
     *
     * @param array<string, array{label: string, type: string, platforms: Platform[]}> $merged
     * @param array<string, mixed>                                                     $meta
     */
    private function mergeProviderFields(array &$merged, PlatformFieldProviderInterface $provider, array $meta): void
    {
        foreach ($provider->getRequiredMetaFields() as $fieldName => $def) {
            if (!empty($meta[$fieldName])) {
                continue;
            }
            if (isset($merged[$fieldName])) {
                $merged[$fieldName]['platforms'][] = $provider->getPlatform();
            } else {
                $merged[$fieldName] = [
                    'label' => $def['label'],
                    'type' => $def['type'],
                    'platforms' => [$provider->getPlatform()],
                ];
            }
        }
    }
}
