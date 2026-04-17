<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

use App\Domain\Enum\Platform;

/**
 * Read-side view model for the article edit page.
 *
 * @phpstan-type SubmissionsMap array<int, array<string, mixed>>
 * @phpstan-type CategoryFieldsMap array<string, mixed>
 */
final readonly class ArticleEditViewModel
{
    /**
     * @param array<int, array<string, mixed>> $submissions        Keyed by articleId → platformValue → ArticleSubmission
     * @param Platform[]                       $availablePlatforms
     * @param array<string, mixed>             $categoryFields     Keyed by Category value
     */
    public function __construct(
        public array $submissions,
        public array $availablePlatforms,
        public array $categoryFields,
    ) {
    }
}
