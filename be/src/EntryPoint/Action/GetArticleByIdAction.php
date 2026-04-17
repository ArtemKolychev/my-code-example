<?php

declare(strict_types=1);

namespace App\EntryPoint\Action;

use App\Application\Query\ArticleProviderInterface;
use App\Domain\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/market/article/{id}', name: 'app_get_article_by_id', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class GetArticleByIdAction extends AbstractController
{
    public function __construct(
        private readonly ArticleProviderInterface $articleProvider,
    ) {
    }

    public function __invoke(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $article = $this->articleProvider->findForUser($id, $user);

        if (!$article) {
            return new JsonResponse(['error' => 'Article not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($article->toArray());
    }
}
