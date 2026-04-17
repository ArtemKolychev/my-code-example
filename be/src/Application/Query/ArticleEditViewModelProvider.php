<?php

declare(strict_types=1);

namespace App\Application\Query;

use App\Application\DTO\Response\ArticleEditViewModel;
use App\Domain\Entity\Article;
use App\Domain\Entity\User;
use App\Domain\Enum\Category;
use App\Domain\Registry\CategoryFieldRegistry;

final readonly class ArticleEditViewModelProvider
{
    public function __construct(
        private ArticleSubmissionQueryServiceInterface $articleSubmissionQueryService,
        private AvailablePlatformsProvider $availablePlatformsProvider,
    ) {
    }

    /**
     * @param Article[] $articles
     */
    public function build(array $articles, User $user): ArticleEditViewModel
    {
        return new ArticleEditViewModel(
            submissions: $this->buildSubmissionsMap($articles),
            availablePlatforms: $this->availablePlatformsProvider->getAvailablePlatforms($user),
            categoryFields: $this->buildCategoryFields(),
        );
    }

    /**
     * @param Article[] $articles
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildSubmissionsMap(array $articles): array
    {
        $allSubmissions = $this->articleSubmissionQueryService->findAllByArticles($articles);

        $submissions = [];
        foreach ($articles as $article) {
            $articleId = $article->getId();
            if (!$articleId) {
                continue;
            }

            $submissions[$articleId] = [];
            foreach ($allSubmissions[$articleId] ?? [] as $submission) {
                $submissions[$articleId][$submission->getPlatform()->value] = $submission;
            }
        }

        return $submissions;
    }

    /** @return array<string, mixed> */
    private function buildCategoryFields(): array
    {
        $fields = [];
        foreach (Category::cases() as $cat) {
            $fields[$cat->value] = CategoryFieldRegistry::getFields($cat);
        }

        return $fields;
    }
}
