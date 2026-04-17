<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Twig\Environment;

class ForgotPasswordService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly MailerInterface $mailer,
        private readonly Environment $environment,
        private readonly string $appUrl,
        private readonly string $mailerFrom,
        private readonly ClockInterface $clock,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
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
        $user->setResetPasswordTokenExpiresAt($this->clock->now()->modify('+1 hour'));

        $this->userRepository->save($user);

        $this->sendResetEmail($user, $rawToken);

        return $rawToken;
    }

    /**
     * Finds the user whose hashed token matches sha256($rawToken) and has not expired.
     */
    public function validateToken(string $rawToken): ?User
    {
        $hashedToken = hash('sha256', $rawToken);

        $user = $this->userRepository->findByResetToken($hashedToken);

        if (null === $user) {
            return null;
        }

        $expiresAt = $user->getResetPasswordTokenExpiresAt();
        if (null === $expiresAt || $expiresAt < $this->clock->now()) {
            return null;
        }

        return $user;
    }

    /**
     * Hashes the plain password, sets it on the user, and clears the reset token.
     */
    public function resetPassword(User $user, string $plainPassword): void
    {
        $hashedPassword = $this->userPasswordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        $this->clearToken($user);
    }

    /**
     * Nullifies the token fields after a successful password reset.
     */
    public function clearToken(User $user): void
    {
        $user->setResetPasswordToken(null);
        $user->setResetPasswordTokenExpiresAt(null);

        $this->userRepository->save($user);
    }

    private function sendResetEmail(User $user, string $rawToken): void
    {
        $resetUrl = rtrim($this->appUrl, '/').'/reset-password/'.$rawToken;

        $htmlBody = $this->environment->render('emails/reset_password.html.twig', [
            'resetUrl' => $resetUrl,
            'user' => $user,
        ]);

        $email = new Email()
            ->from($this->mailerFrom)
            ->to((string) $user->getEmail())
            ->subject('Reset your password')
            ->html($htmlBody);

        $this->mailer->send($email);
    }
}
