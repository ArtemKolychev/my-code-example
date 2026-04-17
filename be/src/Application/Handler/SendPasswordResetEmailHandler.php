<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\SendPasswordResetEmailCommand;
use App\Application\Service\ForgotPasswordService;
use App\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler(handles: SendPasswordResetEmailCommand::class)]
final readonly class SendPasswordResetEmailHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private ForgotPasswordService $forgotPasswordService,
    ) {
    }

    public function __invoke(SendPasswordResetEmailCommand $command): void
    {
        $user = $this->userRepository->findByEmail($command->email);

        if (null === $user) {
            // Silently do nothing — do not leak whether email exists
            return;
        }

        try {
            $this->forgotPasswordService->createResetToken($user);
        } catch (Throwable) {
            // Silently fail — do not leak delivery status
        }
    }
}
