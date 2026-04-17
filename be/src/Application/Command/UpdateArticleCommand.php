<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\Enum\Category;
use App\Domain\Enum\Condition;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Synchronous command: update article fields and optionally add new images.
 *
 * @param UploadedFile[]            $images
 * @param array<string, mixed>|null $metaFields meta fields to merge into the article's existing meta
 */
final readonly class UpdateArticleCommand
{
    /**
     * @param UploadedFile[]            $images
     * @param array<string, mixed>|null $metaFields
     */
    public function __construct(
        public int $articleId,
        public ?string $title,
        public ?string $description,
        public ?float $price,
        public ?Category $category = null,
        public ?Condition $condition = null,
        public ?array $metaFields = null,
        public array $images = [],
    ) {
    }
}
