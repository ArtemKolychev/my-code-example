<?php

declare(strict_types=1);

namespace App\EntryPoint\Action;

use App\Domain\Entity\User;
use App\EntryPoint\Service\Handler\PostArticlesHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/market/article/{ids}/post', name: 'app_post_articles')]
#[IsGranted('ROLE_USER')]
final class PostArticlesAction extends AbstractController
{
    public function __construct(
        private readonly PostArticlesHandler $postArticlesHandler,
    ) {
    }

    public function __invoke(string $ids, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $result = $this->postArticlesHandler->handle($ids, $request, $user);

        foreach ($result->flashes as $type => $message) {
            $this->addFlash($type, $message);
        }

        return $this->redirectToRoute($result->redirectRoute, $result->redirectParams);
    }
}
