<?php

declare(strict_types=1);

namespace App\Auth\Providers;

use App\Auth\Application\Contract\JwtIssuerInterface;
use App\Auth\Application\Contract\OutboxPublisherInterface;
use App\Auth\Application\Contract\UserQueryInterface;
use App\Auth\Domain\User\Repository\UserRepositoryInterface;
use App\Auth\Infrastructure\Console\PublishOutboxCommand;
use App\Auth\Infrastructure\Jwt\LcobucciJwtIssuer;
use App\Auth\Infrastructure\Messaging\AmqpPublisher;
use App\Auth\Infrastructure\Messaging\OutboxPublisher;
use App\Auth\Infrastructure\Messaging\RoutingKeyResolver;
use App\Auth\Infrastructure\Repository\UserQueryRepository;
use App\Auth\Infrastructure\Repository\UserRepository;
use Illuminate\Support\ServiceProvider;

final class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(UserQueryInterface::class, UserQueryRepository::class);
        $this->app->bind(OutboxPublisherInterface::class, OutboxPublisher::class);
        $this->app->singleton(RoutingKeyResolver::class);
        $this->app->singleton(AmqpPublisher::class, fn () => new AmqpPublisher(
            host: (string) config('rabbitmq.host', 'rabbitmq'),
            port: (int) config('rabbitmq.port', 5672),
            user: (string) config('rabbitmq.user', 'guest'),
            password: (string) config('rabbitmq.password', 'guest'),
        ));

        $this->app->bind(JwtIssuerInterface::class, function (): LcobucciJwtIssuer {
            $privateKeyPath = (string) config('jwt.private_key_path');
            $publicKeyPath = (string) config('jwt.public_key_path');

            return new LcobucciJwtIssuer(
                privateKeyPem: file_get_contents($privateKeyPath) ?: throw new \RuntimeException('Cannot read private key'),
                publicKeyPem: file_get_contents($publicKeyPath) ?: throw new \RuntimeException('Cannot read public key'),
            );
        });
    }

    public function boot(): void
    {
        $this->commands([PublishOutboxCommand::class]);
    }
}
