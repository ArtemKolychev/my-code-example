<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\SaveServiceCredentialCommand;
use App\Domain\Entity\ServiceCredential;
use App\Domain\Repository\ServiceCredentialRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: SaveServiceCredentialCommand::class)]
final readonly class SaveServiceCredentialHandler
{
    public function __construct(
        private ServiceCredentialRepositoryInterface $serviceCredentialRepository,
    ) {
    }

    public function __invoke(SaveServiceCredentialCommand $command): void
    {
        $credential = $command->user->getCredentialForService($command->service);

        if (!$credential) {
            $credential = new ServiceCredential();
            $credential->setUser($command->user);
            $credential->setService($command->service);

            if (!$command->showLogin) {
                $credential->setLogin($command->service);
            }
        }

        if ($command->login) {
            $credential->setLogin($command->login);
        }

        if (!empty($command->password)) {
            $credential->setPassword($command->password, $command->appSecret);
        }

        $this->serviceCredentialRepository->save($credential);
    }
}
