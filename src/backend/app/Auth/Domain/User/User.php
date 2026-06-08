<?php

declare(strict_types=1);

namespace App\Auth\Domain\User;

use App\Auth\Domain\User\Event\UserRegistered;
use App\Auth\Domain\User\ValueObject\Email;
use App\Auth\Domain\User\ValueObject\HashedPassword;
use App\Auth\Domain\User\ValueObject\Name;
use App\Auth\Domain\User\ValueObject\UserId;

final class User
{
    /** @var list<UserRegistered> */
    private array $domainEvents = [];

    private function __construct(
        private readonly UserId $id,
        private readonly Email $email,
        private readonly HashedPassword $hashedPassword,
        private readonly Name $name,
    ) {}

    public static function register(
        UserId $id,
        Email $email,
        HashedPassword $hashedPassword,
        Name $name,
        string $jobId,
    ): self {
        $user = new self($id, $email, $hashedPassword, $name);
        $user->domainEvents[] = new UserRegistered(
            jobId: $jobId,
            userId: $id->value,
            email: $email->value,
            occurredAt: new \DateTimeImmutable()->format(\DateTimeInterface::ATOM),
        );

        return $user;
    }

    public static function reconstitute(
        UserId $id,
        Email $email,
        HashedPassword $hashedPassword,
        Name $name,
    ): self {
        return new self($id, $email, $hashedPassword, $name);
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function hashedPassword(): HashedPassword
    {
        return $this->hashedPassword;
    }

    public function name(): Name
    {
        return $this->name;
    }

    /** @return list<object> */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }
}
