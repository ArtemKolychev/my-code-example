<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Command\DeleteArticleCommand;
use App\Application\DTO\Response\WithdrawArticleResult;
use App\Domain\Entity\Article;
use App\Domain\Entity\User;
use App\Domain\Repository\ArticleSubmissionRepositoryInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ArticleWithdrawService
{
    public function __construct(
        private ArticleSubmissionRepositoryInterface $articleSubmissionRepository,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function withdraw(Article $article, User $user): WithdrawArticleResult
    {
        $userId = $user->getId();
        $articleId = $article->getId();

        if (!$userId || !$articleId) {
            return new WithdrawArticleResult(success: false, error: 'Invalid user or article');
        }

        $publishedSubmissions = $this->articleSubmissionRepository->findPublishedByArticle($article);

        if (empty($publishedSubmissions)) {
            return new WithdrawArticleResult(success: false, error: 'Inzerát není publikován na žádné platformě');
        }

        $dispatched = 0;
        foreach ($publishedSubmissions as $submission) {
            $data = $submission->getResultData();
            /** @var array{articleUrl?: string, adUrl?: string} $data */
            $adUrl = $data['articleUrl'] ?? $data['adUrl'] ?? null;
            if (!$adUrl) {
                continue;
            }
            $this->messageBus->dispatch(new DeleteArticleCommand($articleId, $userId, $submission->getPlatform(), $adUrl));
            ++$dispatched;
        }

        if (0 === $dispatched) {
            return new WithdrawArticleResult(success: false, error: 'Inzerát nemá URL na žádné platformě');
        }

        return new WithdrawArticleResult(success: true);
    }
}
