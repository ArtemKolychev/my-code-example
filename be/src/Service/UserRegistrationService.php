<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserRegistrationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function isEmailTaken(string $email): bool
    {
        return null !== $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    public function registerUser(string $email, string $plainPassword, DateTimeImmutable $termsAcceptedAt): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $plainPassword),
        );
        $user->setRoles(['ROLE_USER']);
        $user->setTermsAcceptedAt($termsAcceptedAt);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
