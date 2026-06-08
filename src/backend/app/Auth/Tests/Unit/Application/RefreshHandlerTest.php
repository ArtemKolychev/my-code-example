<?php

declare(strict_types=1);

namespace App\Auth\Tests\Unit\Application;

use App\Auth\Application\Command\Refresh\RefreshCommand;
use App\Auth\Application\Contract\JwtIssuerInterface;
use App\Auth\Application\DTO\Response\TokenPairDto;
use App\Auth\Application\Handler\Refresh\RefreshHandler;
use App\Auth\Domain\User\Repository\UserRepositoryInterface;
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

final class RefreshHandlerTest extends TestCase
{
    private const string USER_UUID = 'user-uuid-1';

    private const string GHOST_UUID = 'ghost-uuid';

    private const string USER_EMAIL = 'user@example.com';

    private const string USER_NAME = 'Test User';

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
    public function issues_new_token_pair_for_valid_refresh_token(): void
    {
        $tokenRow = (object) ['user_id' => self::USER_UUID];
        $user = $this->makeUser(self::USER_UUID);

        $selectMock = Mockery::mock();
        $selectMock->shouldReceive('where')->andReturnSelf();
        $selectMock->shouldReceive('whereNull')->andReturnSelf();
        $selectMock->shouldReceive('first')->andReturn($tokenRow);

        $updateMock = Mockery::mock();
        $updateMock->shouldReceive('where')->andReturnSelf();
        $updateMock->shouldReceive('update')->once()->andReturn(1);

        $callCount = 0;
        DB::shouldReceive('table')
            ->with('auth.refresh_tokens')
            ->twice()
            ->andReturnUsing(function () use (&$callCount, $selectMock, $updateMock) {
                return ++$callCount === 1 ? $selectMock : $updateMock;
            });

        /** @var UserRepositoryInterface&MockInterface $repo */
        $repo = Mockery::mock(UserRepositoryInterface::class);
        $repo->shouldReceive('findById')->with(Mockery::type(UserId::class))->andReturn($user);

        /** @var JwtIssuerInterface&MockInterface $jwt */
        $jwt = Mockery::mock(JwtIssuerInterface::class);
        $jwt->shouldReceive('issueTokenPair')
            ->once()
            ->with(Mockery::type(User::class), '')
            ->andReturn(new TokenPairDto('new.access.token', 'new-refresh-token', 3600));

        $handler = new RefreshHandler($repo, $jwt);
        $result = ($handler)(new RefreshCommand('some-valid-token'));

        self::assertSame('new.access.token', $result->accessToken);
        self::assertSame(3600, $result->expiresIn);
    }

    #[Test]
    public function throws_for_expired_or_revoked_token(): void
    {
        $selectMock = Mockery::mock();
        $selectMock->shouldReceive('where')->andReturnSelf();
        $selectMock->shouldReceive('whereNull')->andReturnSelf();
        $selectMock->shouldReceive('first')->andReturn(null);

        DB::shouldReceive('table')->with('auth.refresh_tokens')->once()->andReturn($selectMock);

        /** @var UserRepositoryInterface&MockInterface $repo */
        $repo = Mockery::mock(UserRepositoryInterface::class);
        $repo->shouldNotReceive('findById');

        /** @var JwtIssuerInterface&MockInterface $jwt */
        $jwt = Mockery::mock(JwtIssuerInterface::class);
        $jwt->shouldNotReceive('issueTokenPair');

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessageMatches('/Invalid or expired refresh token/');

        $handler = new RefreshHandler($repo, $jwt);
        ($handler)(new RefreshCommand('expired-or-revoked-token'));
    }

    #[Test]
    public function throws_when_user_not_found(): void
    {
        $tokenRow = (object) ['user_id' => self::GHOST_UUID];

        $selectMock = Mockery::mock();
        $selectMock->shouldReceive('where')->andReturnSelf();
        $selectMock->shouldReceive('whereNull')->andReturnSelf();
        $selectMock->shouldReceive('first')->andReturn($tokenRow);

        DB::shouldReceive('table')->with('auth.refresh_tokens')->once()->andReturn($selectMock);

        /** @var UserRepositoryInterface&MockInterface $repo */
        $repo = Mockery::mock(UserRepositoryInterface::class);
        $repo->shouldReceive('findById')->andReturn(null);

        /** @var JwtIssuerInterface&MockInterface $jwt */
        $jwt = Mockery::mock(JwtIssuerInterface::class);
        $jwt->shouldNotReceive('issueTokenPair');

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessageMatches('/User not found/');

        $handler = new RefreshHandler($repo, $jwt);
        ($handler)(new RefreshCommand('valid-token-missing-user'));
    }

    private function makeUser(string $id): User
    {
        return User::reconstitute(
            id: UserId::fromString($id),
            email: Email::fromString(self::USER_EMAIL),
            hashedPassword: HashedPassword::fromHash(password_hash('secret', PASSWORD_BCRYPT)),
            name: Name::fromString(self::USER_NAME),
        );
    }
}
