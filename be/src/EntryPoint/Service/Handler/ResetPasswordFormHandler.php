<?php

declare(strict_types=1);

namespace App\EntryPoint\Service\Handler;

use App\Application\DTO\Payload\ResetPasswordPayload;
use App\Application\Service\ForgotPasswordService;
use App\Domain\Entity\User;
use App\EntryPoint\Form\ResetPasswordType;
use App\EntryPoint\Service\Result\ResetPasswordResult;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

final readonly class ResetPasswordFormHandler
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private ForgotPasswordService $forgotPasswordService,
    ) {
    }

    public function handle(User $user, Request $request): ResetPasswordResult
    {
        $payload = new ResetPasswordPayload();
        $form = $this->formFactory->create(ResetPasswordType::class, $payload);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return new ResetPasswordResult(dispatched: false, form: $form);
        }

        $this->forgotPasswordService->resetPassword($user, $payload->password);

        return new ResetPasswordResult(dispatched: true);
    }
}
