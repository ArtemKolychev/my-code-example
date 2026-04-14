<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class ForgotPasswordService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly string $appUrl,
        private readonly string $mailerFrom,
    ) {
    }

    /**
     * Generates a cryptographically secure token, stores its sha256 hash on the user, and sends the reset email.
     * Returns the raw token (only available at creation time — never stored in plain text).
     */
    public function createResetToken(User $user): string
    {
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        $user->setResetPasswordToken($hashedToken);
        $user->setResetPasswordTokenExpiresAt(new DateTimeImmutable('+1 hour'));

        $this->entityManager->flush();

        $this->sendResetEmail($user, $rawToken);

        return $rawToken;
    }

    /**
     * Finds the user whose hashed token matches sha256($rawToken) and has not expired.
     */
    public function validateToken(string $rawToken): ?User
    {
        $hashedToken = hash('sha256', $rawToken);

        $user = $this->userRepository->findOneBy(['resetPasswordToken' => $hashedToken]);

        if (null === $user) {
            return null;
        }

        $expiresAt = $user->getResetPasswordTokenExpiresAt();
        if (null === $expiresAt || $expiresAt < new DateTimeImmutable()) {
            return null;
        }

        return $user;
    }

    /**
     * Nullifies the token fields after a successful password reset.
     */
    public function clearToken(User $user): void
    {
        $user->setResetPasswordToken(null);
        $user->setResetPasswordTokenExpiresAt(null);

        $this->entityManager->flush();
    }

    private function sendResetEmail(User $user, string $rawToken): void
    {
        $resetUrl = rtrim($this->appUrl, '/').'/reset-password/'.$rawToken;

        $htmlBody = $this->twig->render('emails/reset_password.html.twig', [
            'resetUrl' => $resetUrl,
            'user' => $user,
        ]);

        $email = (new Email())
            ->from($this->mailerFrom)
            ->to((string) $user->getEmail())
            ->subject('Reset your password')
            ->html($htmlBody);

        $this->mailer->send($email);
    }
}
