<?php

declare(strict_types=1);

namespace App\Auth\Tests\Unit\Application;

use App\Auth\Application\Command\RegisterUser\RegisterUserCommand;
use App\Auth\Application\Contract\OutboxPublisherInterface;
use App\Auth\Application\Contract\UserQueryInterface;
use App\Auth\Application\Handler\RegisterUser\RegisterUserHandler;
use App\Auth\Domain\User\Repository\UserRepositoryInterface;
use App\Auth\Domain\User\User;
use App\Auth\Domain\User\ValueObject\Email;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RegisterUserHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Log::shouldReceive('warning')->zeroOrMoreTimes()->andReturnNull();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function saves_user_and_publishes_outbox_event(): void
    {
        DB::shouldReceive('transaction')->once()->andReturnUsing(
            fn (callable $cb) => $cb()
        );

        /** @var UserRepositoryInterface&MockInterface $repo */
        $repo = Mockery::mock(UserRepositoryInterface::class);
        $repo->shouldReceive('save')
            ->once()
            ->with(Mockery::type(User::class));

        /** @var UserQueryInterface&MockInterface $query */
        $query = Mockery::mock(UserQueryInterface::class);
        $query->shouldReceive('existsByEmail')
            ->with(Mockery::type(Email::class))
            ->andReturn(false);

        /** @var OutboxPublisherInterface&MockInterface $outbox */
        $outbox = Mockery::mock(OutboxPublisherInterface::class);
        $outbox->shouldReceive('publish')->once();

        $handler = new RegisterUserHandler($repo, $query, $outbox);
        ($handler)(new RegisterUserCommand('job-1', 'alice@test.com', 'pass123', 'Alice'));

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function throws_if_email_already_exists(): void
    {
        /** @var UserRepositoryInterface&MockInterface $repo */
        $repo = Mockery::mock(UserRepositoryInterface::class);
        $repo->shouldNotReceive('save');

        /** @var UserQueryInterface&MockInterface $query */
        $query = Mockery::mock(UserQueryInterface::class);
        $query->shouldReceive('existsByEmail')
            ->with(Mockery::type(Email::class))
            ->andReturn(true);

        /** @var OutboxPublisherInterface&MockInterface $outbox */
        $outbox = Mockery::mock(OutboxPublisherInterface::class);
        $outbox->shouldNotReceive('publish');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/already exists/');

        $handler = new RegisterUserHandler($repo, $query, $outbox);
        ($handler)(new RegisterUserCommand('job-1', 'alice@test.com', 'pass123', 'Alice'));
    }
}
