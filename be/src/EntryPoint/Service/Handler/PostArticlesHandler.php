<?php

declare(strict_types=1);

namespace App\EntryPoint\Service\Handler;

use App\Application\Query\ArticlesByIdsProvider;
use App\Application\Service\ArticlePublishService;
use App\Domain\Entity\User;
use App\Domain\Enum\Platform;
use App\EntryPoint\Service\Result\PostArticlesResult;
use Symfony\Component\HttpFoundation\Request;

final readonly class PostArticlesHandler
{
    public function __construct(
        private ArticlesByIdsProvider $articlesByIdsProvider,
        private ArticlePublishService $articlePublishService,
    ) {
    }

    public function handle(string $ids, Request $request, User $user): PostArticlesResult
    {
        $platformValue = $request->query->getString('platform');
        $platform = Platform::tryFrom($platformValue);

        if (!$platform) {
            return new PostArticlesResult(
                redirectRoute: 'app_edit_article',
                redirectParams: ['id' => $ids],
                flashes: ['error' => 'Invalid platform specified.'],
            );
        }

        $idsArray = array_map(intval(...), explode('-', $ids));
        $articles = $this->articlesByIdsProvider->findByIdsAndUser($idsArray, $user);
        $result = $this->articlePublishService->publishArticles($articles, $user, $platform);

        if ($result->profileIncomplete) {
            return new PostArticlesResult(
                redirectRoute: 'app_edit_article',
                redirectParams: ['id' => $ids],
                flashes: ['profile_incomplete' => 'Vyplňte svůj profil'],
            );
        }

        $flashes = [];
        if ($result->needsInput > 0) {
            $flashes['warning'] = 'Některé inzeráty vyžadují doplnění údajů před publikováním.';
        }
        if ($result->published > 0) {
            $flashes['success'] = 'Inzeráty byly přidány do fronty k publikování.';
        }
        if (0 === $result->published && 0 === $result->needsInput) {
            $flashes['error'] = 'No articles found for publishing. Maybe it was already published?';
        }

        return new PostArticlesResult(
            redirectRoute: 'app_edit_article',
            redirectParams: ['id' => $ids],
            flashes: $flashes,
        );
    }
}
