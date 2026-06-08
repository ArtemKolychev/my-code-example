<?php

declare(strict_types=1);

namespace App\Auth\Tests\Unit\Application;

use App\Auth\Application\Command\Login\LoginCommand;
use App\Auth\Application\Contract\JwtIssuerInterface;
use App\Auth\Application\Contract\OutboxPublisherInterface;
use App\Auth\Application\Contract\UserQueryInterface;
use App\Auth\Application\DTO\Response\TokenPairDto;
use App\Auth\Application\Handler\Login\LoginHandler;
use App\Auth\Domain\User\User;
use App\Auth\Domain\User\ValueObject\Email;
use App\Auth\Domain\User\ValueObject\HashedPassword;
use App\Auth\Domain\User\ValueObject\Name;
use App\Auth\Domain\User\ValueObject\UserId;
use App\Exceptions\UnauthorizedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

final class LoginHandlerTest extends TestCase
{
    private const string USER_ID = 'uuid-1';

    private const string USER_EMAIL = 'a@b.com';

    private const string USER_NAME = 'Alice';

    private const string USER_PASSWORD = 'secret123';

    private const string JOB_ID = 'job-1';

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

    /**
     * @throws Throwable
     */
    #[Test]
    public function issues_token_pair_for_valid_credentials(): void
    {
        DB::shouldReceive('transaction')->once()->andReturnUsing(
            fn (callable $cb) => $cb()
        );

        $hash = password_hash(self::USER_PASSWORD, PASSWORD_BCRYPT);
        $user = User::reconstitute(
            id: UserId::fromString(self::USER_ID),
            email: Email::fromString(self::USER_EMAIL),
            hashedPassword: HashedPassword::fromHash($hash),
            name: Name::fromString(self::USER_NAME),
        );

        /** @var UserQueryInterface&MockInterface $query */
        $query = Mockery::mock(UserQueryInterface::class);
        $query->shouldReceive('findByEmail')
            ->with(Mockery::type(Email::class))
            ->andReturn($user);

        /** @var JwtIssuerInterface&MockInterface $jwtIssuer */
        $jwtIssuer = Mockery::mock(JwtIssuerInterface::class);
        $jwtIssuer->shouldReceive('issueTokenPair')
            ->with(Mockery::type(User::class), Mockery::type('string'))
            ->andReturn(new TokenPairDto('access.token', 'refresh-token', 3600));

        /** @var OutboxPublisherInterface&MockInterface $outbox */
        $outbox = Mockery::mock(OutboxPublisherInterface::class);
        $outbox->shouldReceive('publish')->once();

        $handler = new LoginHandler($query, $jwtIssuer, $outbox);
        $result = ($handler)(new LoginCommand(self::JOB_ID, self::USER_EMAIL, self::USER_PASSWORD));

        self::assertSame('access.token', $result->accessToken);
        self::assertSame(3600, $result->expiresIn);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function throws_on_invalid_credentials(): void
    {
        /** @var UserQueryInterface&MockInterface $query */
        $query = Mockery::mock(UserQueryInterface::class);
        $query->shouldReceive('findByEmail')->andReturn(null);

        /** @var JwtIssuerInterface&MockInterface $jwtIssuer */
        $jwtIssuer = Mockery::mock(JwtIssuerInterface::class);
        $jwtIssuer->shouldNotReceive('issueTokenPair');

        /** @var OutboxPublisherInterface&MockInterface $outbox */
        $outbox = Mockery::mock(OutboxPublisherInterface::class);

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessageMatches('/Invalid credentials\./i');

        $handler = new LoginHandler($query, $jwtIssuer, $outbox);
        ($handler)(new LoginCommand('job-1', 'unknown@test.com', 'wrong'));
    }
}
