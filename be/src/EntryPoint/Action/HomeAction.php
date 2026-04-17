<?php

declare(strict_types=1);

namespace App\EntryPoint\Action;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/', name: 'app_home')]
final class HomeAction extends AbstractController
{
    public function __invoke(): RedirectResponse
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        return $this->redirectToRoute('app_user_profile');
    }
}
