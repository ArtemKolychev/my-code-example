<?php

declare(strict_types=1);

namespace App\Auth\Application\Handler\RegisterUser;

use App\Auth\Application\Command\RegisterUser\RegisterUserCommand;
use App\Auth\Application\Contract\OutboxPublisherInterface;
use App\Auth\Application\Contract\UserQueryInterface;
use App\Auth\Domain\User\Repository\UserRepositoryInterface;
use App\Auth\Domain\User\User;
use App\Auth\Domain\User\ValueObject\Email;
use App\Auth\Domain\User\ValueObject\HashedPassword;
use App\Auth\Domain\User\ValueObject\Name;
use App\Auth\Domain\User\ValueObject\UserId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class RegisterUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserQueryInterface $userQuery,
        private OutboxPublisherInterface $outboxPublisher,
    ) {}

    public function __invoke(RegisterUserCommand $command): void
    {
        $email = Email::fromString($command->email);

        if ($this->userQuery->existsByEmail($email)) {
            Log::warning('handler.register.email_exists', ['email' => $command->email]);
            throw new \DomainException("User with email {$command->email} already exists.");
        }

        $user = User::register(
            id: UserId::generate(),
            email: $email,
            hashedPassword: HashedPassword::fromPlainText($command->password),
            name: Name::fromString($command->name),
            jobId: $command->jobId,
        );

        DB::transaction(function () use ($user): void {
            $this->userRepository->save($user);

            foreach ($user->pullDomainEvents() as $event) {
                $this->outboxPublisher->publish($event);
            }
        });
    }
}
