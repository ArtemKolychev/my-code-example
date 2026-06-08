<?php

declare(strict_types=1);

namespace App\Auth\Tests\Unit\Domain;

use App\Auth\Domain\User\Event\UserRegistered;
use App\Auth\Domain\User\User;
use App\Auth\Domain\User\ValueObject\Email;
use App\Auth\Domain\User\ValueObject\HashedPassword;
use App\Auth\Domain\User\ValueObject\Name;
use App\Auth\Domain\User\ValueObject\UserId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    private const string USER_ID = 'some-uuid';

    private const string USER_EMAIL = 'alice@example.com';

    private const string USER_NAME = 'Alice';

    private const string JOB_ID = 'job-1';

    private const string PASSWORD = 'secret123';

    #[Test]
    public function creates_user_and_records_user_registered_domain_event(): void
    {
        $user = User::register(
            id: UserId::generate(),
            email: Email::fromString(self::USER_EMAIL),
            hashedPassword: HashedPassword::fromPlainText(self::PASSWORD),
            name: Name::fromString(self::USER_NAME),
            jobId: self::JOB_ID,
        );

        self::assertSame(self::USER_EMAIL, $user->email()->value);

        $events = $user->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(UserRegistered::class, $events[0]);
        self::assertSame('job-1', $events[0]->jobId);
        self::assertSame('alice@example.com', $events[0]->email);
    }

    #[Test]
    public function pull_domain_events_clears_events(): void
    {
        $user = User::register(
            id: UserId::generate(),
            email: Email::fromString(self::USER_EMAIL),
            hashedPassword: HashedPassword::fromPlainText(self::PASSWORD),
            name: Name::fromString(self::USER_NAME),
            jobId: self::JOB_ID,
        );

        $user->pullDomainEvents();
        $events = $user->pullDomainEvents();

        self::assertCount(0, $events);
    }

    #[Test]
    public function email_value_object_rejects_invalid_email(): void
    {
        $this->expectException(\DomainException::class);
        Email::fromString('not-an-email');
    }

    #[Test]
    public function hashed_password_verifies_correct_plain_text(): void
    {
        $hp = HashedPassword::fromPlainText('secret123');
        self::assertTrue($hp->verify('secret123'));
        self::assertFalse($hp->verify('wrong'));
    }

    #[Test]
    public function reconstitute_does_not_record_domain_events(): void
    {
        $user = User::reconstitute(
            id: UserId::fromString(self::USER_ID),
            email: Email::fromString(self::USER_EMAIL),
            hashedPassword: HashedPassword::fromHash(password_hash('secret', PASSWORD_BCRYPT)),
            name: Name::fromString(self::USER_NAME),
        );

        self::assertCount(0, $user->pullDomainEvents());
    }
}
