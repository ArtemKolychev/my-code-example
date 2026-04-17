<?php

declare(strict_types=1);

namespace App\EntryPoint\Action;

use App\Application\Command\RemoveArticleCommand;
use App\Domain\Entity\Article;
use App\Domain\Security\Permission;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/market/article/{id}/delete', name: 'app_delete_article', methods: ['POST'])]
#[IsGranted(Permission::ARTICLE_DELETE, subject: 'article')]
final class DeleteArticleAction extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(#[MapEntity(id: 'id')] Article $article): RedirectResponse
    {
        $this->messageBus->dispatch(new RemoveArticleCommand((int) $article->getId()));
        $this->addFlash('success', 'Inzerát byl smazán.');

        return $this->redirectToRoute('app_list_articles');
    }
}
