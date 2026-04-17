<?php

declare(strict_types=1);

namespace App\EntryPoint\Service;

use App\Application\Command\SaveServiceCredentialCommand;
use App\Domain\Entity\User;
use App\EntryPoint\Form\ServiceCredentialType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ServiceCredentialManager
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private FormFactoryInterface $formFactory,
        #[Autowire('%kernel.secret%')]
        private string $appSecret,
    ) {
    }

    /**
     * Build, handle, and optionally persist a service credential form.
     *
     * @param array{plain_password?: bool, show_login?: bool} $formOptions
     *
     * @return array{form: FormInterface<array<string, mixed>>, saved: bool, hasCredential: bool}
     */
    public function handleCredentialForm(
        string $service,
        User $user,
        Request $request,
        array $formOptions = [],
    ): array {
        $credential = $user->getCredentialForService($service);
        $showLogin = $formOptions['show_login'] ?? true;

        $form = $this->formFactory->createNamed(
            $service.'_credential',
            ServiceCredentialType::class,
            $showLogin ? ['login' => $credential?->getLogin()] : [],
            [
                'has_existing_credential' => null !== $credential,
                ...$formOptions,
            ],
        );

        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return ['form' => $form, 'saved' => false, 'hasCredential' => null !== $credential];
        }

        /** @var array{login?: string|null, password?: string|null} $data */
        $data = $form->getData();

        $this->messageBus->dispatch(new SaveServiceCredentialCommand(
            user: $user,
            service: $service,
            login: $data['login'] ?? null,
            password: !empty($data['password']) ? $data['password'] : null,
            showLogin: $showLogin,
            appSecret: $this->appSecret,
        ));

        return ['form' => $form, 'saved' => true, 'hasCredential' => true];
    }
}
