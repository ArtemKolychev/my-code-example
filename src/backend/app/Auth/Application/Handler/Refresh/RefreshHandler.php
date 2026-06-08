<?php

declare(strict_types=1);

namespace App\Auth\Application\Handler\Refresh;

use App\Auth\Application\Command\Refresh\RefreshCommand;
use App\Auth\Application\Contract\JwtIssuerInterface;
use App\Auth\Application\DTO\Response\TokenPairDto;
use App\Auth\Domain\User\Repository\UserRepositoryInterface;
use App\Auth\Domain\User\ValueObject\UserId;
use App\Exceptions\UnauthorizedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class RefreshHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private JwtIssuerInterface $jwtIssuer,
    ) {}

    public function __invoke(RefreshCommand $command): TokenPairDto
    {
        $hash = hash('sha256', $command->refreshToken);

        $tokenRow = DB::table('auth.refresh_tokens')
            ->where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($tokenRow === null) {
            Log::warning('handler.refresh.invalid_token');
            throw new UnauthorizedException('Invalid or expired refresh token.');
        }

        $user = $this->userRepository->findById(UserId::fromString((string) $tokenRow->user_id));

        if ($user === null) {
            Log::warning('handler.refresh.user_not_found');
            throw new UnauthorizedException('User not found.');
        }

        DB::table('auth.refresh_tokens')
            ->where('token_hash', $hash)
            ->update(['revoked_at' => now()]);

        return $this->jwtIssuer->issueTokenPair($user, '');
    }
}
