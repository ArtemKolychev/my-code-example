<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ForgotPasswordService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;

class ForgotPasswordServiceTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private MailerInterface&MockObject $mailer;
    private Environment&MockObject $twig;
    private ForgotPasswordService $service;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->twig = $this->createMock(Environment::class);

        $this->service = new ForgotPasswordService(
            $this->userRepository,
            $this->entityManager,
            $this->mailer,
            $this->twig,
            appUrl: 'https://example.com',
            mailerFrom: 'noreply@example.com',
        );
    }

    public function testCreateResetTokenGeneratesTokenAndSetsExpiryAndSendsEmail(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');

        $this->entityManager->expects($this->once())->method('flush');
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

        $user = new User();
        $user->setEmail('user@example.com');
        $user->setResetPasswordToken($hashedToken);
        $user->setResetPasswordTokenExpiresAt(new DateTimeImmutable('+30 minutes'));

        $this->userRepository
            ->method('findOneBy')
            ->with(['resetPasswordToken' => $hashedToken])
            ->willReturn($user);

        $result = $this->service->validateToken($rawToken);

        $this->assertSame($user, $result);
    }

    public function testValidateTokenReturnsNullWhenTokenExpired(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        $user = new User();
        $user->setEmail('user@example.com');
        $user->setResetPasswordToken($hashedToken);
        $user->setResetPasswordTokenExpiresAt(new DateTimeImmutable('-1 hour'));

        $this->userRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['resetPasswordToken' => $hashedToken])
            ->willReturn($user);

        $result = $this->service->validateToken($rawToken);

        // Token was found in DB but rejected due to expiry
        $this->assertNull($result);
    }

    public function testValidateTokenReturnsNullWhenTokenNotFound(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        $this->userRepository
            ->method('findOneBy')
            ->with(['resetPasswordToken' => $hashedToken])
            ->willReturn(null);

        $result = $this->service->validateToken($rawToken);

        $this->assertNull($result);
    }

    public function testClearTokenNullifiesBothFields(): void
    {
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setResetPasswordToken(hash('sha256', 'sometoken'));
        $user->setResetPasswordTokenExpiresAt(new DateTimeImmutable('+1 hour'));

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->clearToken($user);

        $this->assertNull($user->getResetPasswordToken());
        $this->assertNull($user->getResetPasswordTokenExpiresAt());
    }
}
