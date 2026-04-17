<?php

declare(strict_types=1);

namespace App\EntryPoint\Action;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/terms', name: 'app_terms')]
final class TermsAction extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('legal/terms.html.twig');
    }
}
