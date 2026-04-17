<?php

declare(strict_types=1);

namespace App\EntryPoint\Action;

use App\EntryPoint\Service\Handler\RegisterFormHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/register', name: 'app_register')]
final class RegisterAction extends AbstractController
{
    public function __construct(
        private readonly RegisterFormHandler $registerFormHandler,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $form = $this->registerFormHandler->createForm();
        $result = $this->registerFormHandler->handle($form, $request);

        if ($result->dispatched) {
            $this->addFlash('success', 'Your account has been created. You can now log in.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}
