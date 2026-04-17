<?php

declare(strict_types=1);

namespace App\EntryPoint\Action;

use App\EntryPoint\Service\Handler\ForgotPasswordFormHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
final class ForgotPasswordAction extends AbstractController
{
    public function __construct(
        private readonly ForgotPasswordFormHandler $forgotPasswordFormHandler,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $form = $this->forgotPasswordFormHandler->createForm();
        $result = $this->forgotPasswordFormHandler->handle($form, $request);

        if ($result->dispatched) {
            $this->addFlash('success', 'If that email address is in our database, we will send you an email to reset your password.');

            return $this->redirectToRoute('app_forgot_password');
        }

        return $this->render('security/forgot_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
