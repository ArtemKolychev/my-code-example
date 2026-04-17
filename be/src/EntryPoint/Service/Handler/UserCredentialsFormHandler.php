<?php

declare(strict_types=1);

namespace App\EntryPoint\Service\Handler;

use App\Domain\Entity\User;
use App\EntryPoint\Service\Result\UserCredentialsResult;
use App\EntryPoint\Service\ServiceCredentialManager;
use Symfony\Component\HttpFoundation\Request;

final readonly class UserCredentialsFormHandler
{
    /** @var array<string, array{plain_password?: bool, show_login?: bool}> */
    private const array CREDENTIAL_SERVICES = [
        'sbazar' => [],
        'vinted' => [],
        'bazos' => ['plain_password' => true],
        'motoinzerce' => ['plain_password' => true, 'show_login' => false],
    ];

    public function __construct(
        private ServiceCredentialManager $serviceCredentialManager,
    ) {
    }

    public function handle(User $user, Request $request): UserCredentialsResult
    {
        $credentialForms = [];
        $hasCredential = [];

        foreach (self::CREDENTIAL_SERVICES as $service => $options) {
            $result = $this->serviceCredentialManager->handleCredentialForm($service, $user, $request, $options);

            if ($result['saved']) {
                return new UserCredentialsResult(
                    redirectNeeded: true,
                    credentialForms: [],
                    hasCredential: [],
                    savedService: $service,
                );
            }

            $credentialForms[$service] = $result['form'];
            $hasCredential[$service] = $result['hasCredential'];
        }

        return new UserCredentialsResult(
            redirectNeeded: false,
            credentialForms: $credentialForms,
            hasCredential: $hasCredential,
        );
    }
}
