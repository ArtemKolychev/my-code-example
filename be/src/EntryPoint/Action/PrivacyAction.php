<?php

declare(strict_types=1);

namespace App\EntryPoint\Action;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/privacy', name: 'app_privacy')]
final class PrivacyAction extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('legal/privacy.html.twig');
    }
}
