<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Command\PublishArticleCommand;
use App\Application\DTO\Response\PublishArticlesResult;
use App\Domain\Entity\Article;
use App\Domain\Entity\User;
use App\Domain\Enum\Platform;
use App\Domain\Registry\CategoryFieldRegistry;
use App\Domain\Repository\ArticleRepositoryInterface;
use App\Domain\Repository\ArticleSubmissionRepositoryInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ArticlePublishService
{
    public function __construct(
        private ArticleRepositoryInterface $articleRepository,
        private ArticleSubmissionRepositoryInterface $articleSubmissionRepository,
        private MessageBusInterface $messageBus,
        private PlatformFieldAggregatorInterface $platformFieldAggregator,
    ) {
    }

    /**
     * Publish articles to a platform. Returns counts of published and needs-input articles.
     * Returns profileIncomplete=true if user profile is not filled in.
     *
     * @param Article[] $articles
     */
    public function publishArticles(array $articles, User $user, Platform $platform): PublishArticlesResult
    {
        if (!$user->isProfileComplete()) {
            return new PublishArticlesResult(published: 0, needsInput: 0, profileIncomplete: true);
        }

        $articles = $this->filterPublishableForPlatform($articles, $platform);

        $publishedCount = 0;
        $needsInputCount = 0;

        foreach ($articles as $article) {
            $articleId = $article->getId();
            $userId = $user->getId();
            if (!$articleId) {
                continue;
            }
            if (!$userId) {
                continue;
            }

            $missingFieldNames = $this->platformFieldAggregator->getMissingFieldsForPlatform($article, $platform);
            if ($this->dispatchOrSetPending($article, $articleId, $userId, $platform, $missingFieldNames)) {
                ++$publishedCount;
            } else {
                ++$needsInputCount;
            }
        }

        return new PublishArticlesResult(published: $publishedCount, needsInput: $needsInputCount);
    }

    /**
     * @param Article[] $articles
     *
     * @return Article[]
     */
    private function filterPublishableForPlatform(array $articles, Platform $platform): array
    {
        return array_filter($articles, function (Article $a) use ($platform): bool {
            $existing = $this->articleSubmissionRepository->findByArticleAndPlatform($a, $platform);

            if (!$existing) {
                return true;
            }

            return $existing->getStatus()->canRetry();
        });
    }

    /**
     * @param string[] $missingFieldNames
     *
     * @throws ExceptionInterface
     */
    private function dispatchOrSetPending(Article $article, int $articleId, int $userId, Platform $platform, array $missingFieldNames): bool
    {
        if (empty($missingFieldNames)) {
            $this->messageBus->dispatch(new PublishArticleCommand($articleId, $userId, $platform));

            return true;
        }

        $fields = $this->buildMissingFieldsPrompt($article, $missingFieldNames);
        if (empty($fields)) {
            $this->messageBus->dispatch(new PublishArticleCommand($articleId, $userId, $platform));

            return true;
        }

        $article->setPendingInput([
            'inputType' => 'meta_fields',
            'jobId' => $platform->value.'_'.uniqid(),
            'fields' => $fields,
            'inputPrompt' => 'Vyplňte povinné údaje pro '.$platform->name,
        ]);
        $this->articleRepository->save($article);

        return false;
    }

    /**
     * @param array{label: string, type: string, options?: array<string, string>} $def
     *
     * @return array{label: string, type: string, options?: array<string, string>}
     */
    private function buildFieldEntry(array $def): array
    {
        $entry = ['label' => $def['label'], 'type' => $def['type']];
        if (isset($def['options'])) {
            $entry['options'] = $def['options'];
        }

        return $entry;
    }

    /**
     * @param string[] $missingFieldNames
     *
     * @return array<string, array{label: string, type: string, options?: array<string, string>}>
     */
    private function buildMissingFieldsPrompt(Article $article, array $missingFieldNames): array
    {
        $categoryFieldMap = [];
        $category = $article->getCategory();
        if ($category) {
            $categoryFieldMap = array_column(CategoryFieldRegistry::getFields($category), null, 'key');
        }

        $fields = [];
        foreach ($missingFieldNames as $fieldName) {
            if (!empty($categoryFieldMap) && !isset($categoryFieldMap[$fieldName])) {
                continue;
            }
            $def = $categoryFieldMap[$fieldName] ?? null;
            if ($def) {
                $fields[$fieldName] = $this->buildFieldEntry($def);
            }
        }

        return $fields;
    }
}
