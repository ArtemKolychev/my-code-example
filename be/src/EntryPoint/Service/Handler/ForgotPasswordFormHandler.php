<?php

declare(strict_types=1);

namespace App\EntryPoint\Service\Handler;

use App\Application\Command\SendPasswordResetEmailCommand;
use App\Application\DTO\Payload\ForgotPasswordPayload;
use App\EntryPoint\Form\ForgotPasswordType;
use App\EntryPoint\Service\Result\ForgotPasswordResult;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ForgotPasswordFormHandler
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private MessageBusInterface $messageBus,
    ) {
    }

    /** @return FormInterface<ForgotPasswordPayload|null> */
    public function createForm(): FormInterface
    {
        return $this->formFactory->create(ForgotPasswordType::class);
    }

    /** @param FormInterface<ForgotPasswordPayload|null> $form */
    public function handle(FormInterface $form, Request $request): ForgotPasswordResult
    {
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return new ForgotPasswordResult(dispatched: false);
        }

        /** @var ForgotPasswordPayload $payload */
        $payload = $form->getData();

        $this->messageBus->dispatch(new SendPasswordResetEmailCommand((string) $payload->email));

        return new ForgotPasswordResult(dispatched: true);
    }
}
