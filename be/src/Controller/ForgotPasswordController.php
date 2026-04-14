<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\ForgotPasswordService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

class ForgotPasswordController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ForgotPasswordService $forgotPasswordService,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $email = (string) $request->request->get('email', '');
            $user = $this->userRepository->findOneBy(['email' => $email]);

            // Always show the same message to prevent user enumeration
            if (null !== $user) {
                try {
                    $this->forgotPasswordService->createResetToken($user);
                } catch (Throwable) {
                    // Silently fail — do not leak whether the email exists or email delivery succeeded
                }
            }

            $this->addFlash('success', 'If that email address is in our database, we will send you an email to reset your password.');

            return $this->redirectToRoute('app_forgot_password');
        }

        return $this->render('security/forgot_password.html.twig');
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(Request $request, string $token): Response
    {
        $user = $this->forgotPasswordService->validateToken($token);

        if (null === $user) {
            $this->addFlash('danger', 'This password reset link is invalid or has expired.');

            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $newPassword = (string) $request->request->get('password', '');
            $confirmPassword = (string) $request->request->get('confirm_password', '');

            if ('' === $newPassword) {
                $this->addFlash('danger', 'Password cannot be empty.');

                return $this->render('security/reset_password.html.twig', ['token' => $token]);
            }

            if ($newPassword !== $confirmPassword) {
                $this->addFlash('danger', 'Passwords do not match.');

                return $this->render('security/reset_password.html.twig', ['token' => $token]);
            }

            $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);

            $this->forgotPasswordService->clearToken($user);

            $this->addFlash('success', 'Your password has been reset. You can now log in.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', ['token' => $token]);
    }
}
