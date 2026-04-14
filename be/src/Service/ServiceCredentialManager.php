<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ServiceCredential;
use App\Entity\User;
use App\Form\ServiceCredentialType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

class ServiceCredentialManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FormFactoryInterface $formFactory,
        #[Autowire('%kernel.secret%')]
        private readonly string $appSecret,
    ) {
    }

    /**
     * Build, handle, and optionally persist a service credential form.
     *
     * @param array{plain_password?: bool, show_login?: bool} $formOptions
     *
     * @return array{form: FormInterface, saved: bool, hasCredential: bool}
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

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{login?: string|null, password?: string|null} $data */
            $data = $form->getData();

            if (!$credential) {
                $credential = new ServiceCredential();
                $credential->setUser($user);
                $credential->setService($service);

                if (!$showLogin) {
                    $credential->setLogin($service);
                }
            }

            if ($data['login'] ?? null) {
                $credential->setLogin($data['login']);
            }

            if (!empty($data['password'])) {
                $credential->setPassword($data['password'], $this->appSecret);
            }

            $this->em->persist($credential);
            $this->em->flush();

            return ['form' => $form, 'saved' => true, 'hasCredential' => true];
        }

        return ['form' => $form, 'saved' => false, 'hasCredential' => null !== $credential];
    }
}
