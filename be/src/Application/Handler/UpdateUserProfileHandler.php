<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\UpdateUserProfileCommand;
use App\Domain\Exception\UserNotFoundException;
use App\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: UpdateUserProfileCommand::class)]
final readonly class UpdateUserProfileHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function __invoke(UpdateUserProfileCommand $command): void
    {
        $user = $this->userRepository->findById($command->userId);

        if (!$user) {
            throw UserNotFoundException::forId($command->userId);
        }

        $user->setName($command->name);
        $user->setAddress($command->address);
        $user->setZip($command->zip);
        $user->setPhone($command->phone);

        $this->userRepository->save($user);
    }
}
