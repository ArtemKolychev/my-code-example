<?php

declare(strict_types=1);

namespace App\EntryPoint\Action;

use App\Application\Service\ForgotPasswordService;
use App\EntryPoint\Service\Handler\ResetPasswordFormHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
final class ResetPasswordAction extends AbstractController
{
    public function __construct(
        private readonly ForgotPasswordService $forgotPasswordService,
        private readonly ResetPasswordFormHandler $resetPasswordFormHandler,
    ) {
    }

    public function __invoke(Request $request, string $token): Response
    {
        $user = $this->forgotPasswordService->validateToken($token);

        if (null === $user) {
            $this->addFlash('danger', 'This password reset link is invalid or has expired.');

            return $this->redirectToRoute('app_forgot_password');
        }

        $result = $this->resetPasswordFormHandler->handle($user, $request);

        if ($result->dispatched) {
            $this->addFlash('success', 'Your password has been reset. You can now log in.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'token' => $token,
            'resetForm' => $result->form,
        ]);
    }
}
