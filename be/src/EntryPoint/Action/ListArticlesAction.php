<?php

declare(strict_types=1);

namespace App\EntryPoint\Action;

use App\Application\Query\ArticleListViewModelProvider;
use App\Domain\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/market/articles/edit', name: 'app_list_articles')]
#[IsGranted('ROLE_USER')]
final class ListArticlesAction extends AbstractController
{
    public function __construct(
        private readonly ArticleListViewModelProvider $articleListViewModelProvider,
    ) {
    }

    public function __invoke(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $vm = $this->articleListViewModelProvider->getForUser($user);

        return $this->render('market/index.html.twig', [
            'user' => $user,
            'articles' => $vm->articles,
            'submissions' => $vm->submissions,
            'tokenBalance' => $vm->tokenBalance,
        ]);
    }
}
