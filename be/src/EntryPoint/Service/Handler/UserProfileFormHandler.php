<?php

declare(strict_types=1);

namespace App\EntryPoint\Service\Handler;

use App\Application\Command\UpdateUserProfileCommand;
use App\Application\DTO\Payload\UserProfilePayload;
use App\Domain\Entity\User;
use App\EntryPoint\Form\UserProfileType;
use App\EntryPoint\Service\Result\UserProfileFormResult;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class UserProfileFormHandler
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function handle(User $user, Request $request): UserProfileFormResult
    {
        $payload = UserProfilePayload::fromUser($user);
        $profileForm = $this->formFactory->create(UserProfileType::class, $payload);
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            /** @var UserProfilePayload $data */
            $data = $profileForm->getData();
            $this->messageBus->dispatch(new UpdateUserProfileCommand(
                userId: (int) $user->getId(),
                name: $data->name,
                address: $data->address,
                zip: $data->zip,
                phone: $data->phone,
            ));

            return new UserProfileFormResult(redirectNeeded: true, profileForm: $profileForm);
        }

        return new UserProfileFormResult(redirectNeeded: false, profileForm: $profileForm);
    }
}
