<?php

declare(strict_types=1);

namespace App\Auth\Application\Handler\Login;

use App\Auth\Application\Command\Login\LoginCommand;
use App\Auth\Application\Contract\JwtIssuerInterface;
use App\Auth\Application\Contract\OutboxPublisherInterface;
use App\Auth\Application\Contract\UserQueryInterface;
use App\Auth\Application\DTO\Response\TokenPairDto;
use App\Auth\Domain\User\Event\UserLoggedIn;
use App\Auth\Domain\User\ValueObject\Email;
use App\Exceptions\UnauthorizedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class LoginHandler
{
    public function __construct(
        private UserQueryInterface $userQuery,
        private JwtIssuerInterface $jwtIssuer,
        private OutboxPublisherInterface $outboxPublisher,
    ) {}

    public function __invoke(LoginCommand $command): TokenPairDto
    {
        $email = Email::fromString($command->email);
        $user = $this->userQuery->findByEmail($email);

        if ($user === null || ! $user->hashedPassword()->verify($command->password)) {
            Log::warning('handler.login.invalid_credentials', ['email' => $command->email]);
            throw new UnauthorizedException('Invalid credentials.');
        }

        $tokenPair = $this->jwtIssuer->issueTokenPair($user, $command->jobId);

        DB::transaction(function () use ($user, $tokenPair, $command): void {
            $this->outboxPublisher->publish(new UserLoggedIn(
                jobId: $command->jobId,
                userId: $user->id()->value,
                accessToken: $tokenPair->accessToken,
                refreshToken: $tokenPair->refreshToken,
                expiresIn: $tokenPair->expiresIn,
                occurredAt: (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            ));
        });

        return $tokenPair;
    }
}
