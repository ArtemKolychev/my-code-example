<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserRegistrationService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly ClockInterface $clock,
    ) {
    }

    public function isEmailTaken(string $email): bool
    {
        return null !== $this->userRepository->findByEmail($email);
    }

    public function registerUser(string $email, string $plainPassword): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword(
            $this->userPasswordHasher->hashPassword($user, $plainPassword),
        );
        $user->setRoles(['ROLE_USER']);
        $user->setTermsAcceptedAt($this->clock->now());

        $this->userRepository->save($user);

        return $user;
    }
}
