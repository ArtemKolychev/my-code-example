<?php

declare(strict_types=1);

namespace App\Application\Query;

use App\Application\DTO\Response\ArticleListViewModel;
use App\Application\DTO\Response\ArticleReadModel;
use App\Domain\Entity\User;

final readonly class ArticleListViewModelProvider
{
    public function __construct(
        private ArticleSubmissionQueryServiceInterface $articleSubmissionQueryService,
    ) {
    }

    public function getForUser(User $user): ArticleListViewModel
    {
        $articles = $user->getArticles()->toArray();
        $submissionsByArticle = $this->articleSubmissionQueryService->findAllByArticles($articles);

        $submissions = [];
        foreach ($submissionsByArticle as $articleId => $articleSubs) {
            foreach ($articleSubs as $sub) {
                $submissions[$articleId][$sub->getPlatform()->value] = $sub;
            }
        }

        $readModels = array_map(
            ArticleReadModel::fromArticle(...),
            $articles,
        );

        return new ArticleListViewModel(
            articles: $readModels,
            submissions: $submissions,
            tokenBalance: $user->getTokenBalance(),
        );
    }
}
