<?php

declare(strict_types=1);

namespace App\EntryPoint\Action;

use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/logout', name: 'app_logout')]
final class LogoutAction extends AbstractController
{
    public function __invoke(): never
    {
        throw new LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
