<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Application\Service\ForgotPasswordService;
use App\Domain\Repository\UserRepositoryInterface;
use App\Tests\Shared\Mother\UserMother;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Twig\Environment;

class ForgotPasswordServiceTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private MailerInterface&MockObject $mailer;
    private Environment&MockObject $twig;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private ForgotPasswordService $service;

    public function testCreateResetTokenGeneratesTokenAndSetsExpiryAndSendsEmail(): void
    {
        $user = UserMother::withEmail('user@example.com');

        $this->userRepository->expects($this->once())->method('save')->with($user);
        $this->twig->method('render')->willReturn('<html>reset</html>');
        $this->mailer->expects($this->once())->method('send');

        $rawToken = $this->service->createResetToken($user);

        $this->assertNotEmpty($rawToken);
        $this->assertSame(hash('sha256', $rawToken), $user->getResetPasswordToken());
        $this->assertNotNull($user->getResetPasswordTokenExpiresAt());
        $this->assertGreaterThan(new DateTimeImmutable(), $user->getResetPasswordTokenExpiresAt());
    }

    public function testValidateTokenReturnsUserWhenTokenValidAndNotExpired(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        $user = UserMother::withEmail('user@example.com');
        $user->setResetPasswordToken($hashedToken);
        $user->setResetPasswordTokenExpiresAt(new DateTimeImmutable('+30 minutes'));

        $this->userRepository
            ->method('findByResetToken')
            ->with($hashedToken)
            ->willReturn($user);

        $result = $this->service->validateToken($rawToken);

        $this->assertSame($user, $result);
    }

    public function testValidateTokenReturnsNullWhenTokenExpired(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        $user = UserMother::withEmail('user@example.com');
        $user->setResetPasswordToken($hashedToken);
        $user->setResetPasswordTokenExpiresAt(new DateTimeImmutable('-1 hour'));

        $this->userRepository
            ->expects($this->once())
            ->method('findByResetToken')
            ->with($hashedToken)
            ->willReturn($user);

        $result = $this->service->validateToken($rawToken);

        $this->assertNull($result);
    }

    public function testValidateTokenReturnsNullWhenTokenNotFound(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        $this->userRepository
            ->method('findByResetToken')
            ->with($hashedToken)
            ->willReturn(null);

        $result = $this->service->validateToken($rawToken);

        $this->assertNull($result);
    }

    public function testClearTokenNullifiesBothFields(): void
    {
        $user = UserMother::withEmail('user@example.com');
        $user->setResetPasswordToken(hash('sha256', 'sometoken'));
        $user->setResetPasswordTokenExpiresAt(new DateTimeImmutable('+1 hour'));

        $this->userRepository->expects($this->once())->method('save')->with($user);

        $this->service->clearToken($user);

        $this->assertNull($user->getResetPasswordToken());
        $this->assertNull($user->getResetPasswordTokenExpiresAt());
    }

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->twig = $this->createMock(Environment::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);

        $this->service = new ForgotPasswordService(
            $this->userRepository,
            $this->mailer,
            $this->twig,
            appUrl: 'https://example.com',
            mailerFrom: 'noreply@example.com',
            clock: new Clock(),
            userPasswordHasher: $this->passwordHasher,
        );
    }
}
