<?php

declare(strict_types=1);

namespace App\EntryPoint\Action;

use App\Application\Service\ArticleWithdrawService;
use App\Domain\Entity\Article;
use App\Domain\Entity\User;
use App\Domain\Security\Permission;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/market/article/{id}/withdraw', name: 'app_withdraw_article', methods: ['POST'])]
#[IsGranted(Permission::ARTICLE_WITHDRAW, subject: 'article')]
final class WithdrawArticleAction extends AbstractController
{
    public function __construct(
        private readonly ArticleWithdrawService $articleWithdrawService,
    ) {
    }

    public function __invoke(#[MapEntity(id: 'id')] Article $article, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('withdraw_article', (string) $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        /** @var User $user */
        $user = $this->getUser();
        $result = $this->articleWithdrawService->withdraw($article, $user);

        if (!$result->success) {
            $statusCode = str_contains($result->error ?? '', 'Invalid') ? 500 : 400;

            return new JsonResponse(['error' => $result->error ?? 'Unknown error'], $statusCode);
        }

        return new JsonResponse(['success' => true]);
    }
}
