<?php

declare(strict_types=1);

namespace App\EntryPoint\Service\Handler;

use App\Application\Service\UserRegistrationService;
use App\EntryPoint\Form\RegisterType;
use App\EntryPoint\Service\Result\RegisterResult;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

final readonly class RegisterFormHandler
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private UserRegistrationService $userRegistrationService,
    ) {
    }

    /** @return FormInterface<array<string, mixed>|null> */
    public function createForm(): FormInterface
    {
        return $this->formFactory->create(RegisterType::class);
    }

    /** @param FormInterface<array<string, mixed>|null> $form */
    public function handle(FormInterface $form, Request $request): RegisterResult
    {
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return new RegisterResult(dispatched: false);
        }

        $email = $form->get('email')->getData();
        $plainPassword = $form->get('password')->getData();

        if (is_string($email) && is_string($plainPassword)) {
            $this->userRegistrationService->registerUser($email, $plainPassword);
        }

        return new RegisterResult(dispatched: true);
    }
}
