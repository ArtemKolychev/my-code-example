<?php

declare(strict_types=1);

namespace App\EntryPoint\Action;

use App\Application\Query\ArticleEditViewModelProvider;
use App\Application\Query\ArticlesByIdsProvider;
use App\Domain\Entity\User;
use App\EntryPoint\Service\Handler\ArticleEditFormHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/market/article/{id}/edit', name: 'app_edit_article')]
#[IsGranted('ROLE_USER')]
final class EditArticleAction extends AbstractController
{
    public function __construct(
        private readonly ArticlesByIdsProvider $articlesByIdsProvider,
        private readonly ArticleEditFormHandler $articleEditFormHandler,
        private readonly ArticleEditViewModelProvider $articleEditViewModelProvider,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $articles = $this->articlesByIdsProvider->findByIdsAndUser(
            array_map(intval(...), explode('-', $id)),
            $user,
        );

        $formResult = $this->articleEditFormHandler->handle($articles, $request);
        if ($formResult->dispatched) {
            $this->addFlash('success', 'Article updated successfully!');

            return $this->redirectToRoute('app_edit_article', ['id' => $id]);
        }

        $viewModel = $this->articleEditViewModelProvider->build($articles, $user);

        return $this->render('market/edit_article.html.twig', [
            'forms' => $formResult->forms,
            'submissions' => $viewModel->submissions,
            'availablePlatforms' => $viewModel->availablePlatforms,
            'categoryFields' => $viewModel->categoryFields,
        ]);
    }
}
