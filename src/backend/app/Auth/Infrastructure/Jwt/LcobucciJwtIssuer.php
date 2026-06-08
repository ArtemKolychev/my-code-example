<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Jwt;

use App\Auth\Application\Contract\JwtIssuerInterface;
use App\Auth\Application\DTO\Response\TokenPairDto;
use App\Auth\Domain\User\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Ramsey\Uuid\Uuid;

final readonly class LcobucciJwtIssuer implements JwtIssuerInterface
{
    private const int ACCESS_TOKEN_TTL_SECONDS = 3600;

    private const int REFRESH_TOKEN_TTL_SECONDS = 2592000;

    /**
     * @param  non-empty-string  $privateKeyPem
     * @param  non-empty-string  $publicKeyPem
     * @param  non-empty-string  $issuer
     * @param  non-empty-string  $audience
     */
    public function __construct(
        private string $privateKeyPem,
        private string $publicKeyPem,
        private string $issuer = 'auth-module',
        private string $audience = 'core-platform',
    ) {}

    public function issueTokenPair(User $user, string $jobId): TokenPairDto
    {
        Log::debug('jwt.issuing', ['userId' => $user->id()->value, 'jobId' => $jobId]);

        try {
            $config = Configuration::forAsymmetricSigner(
                new Sha256,
                InMemory::plainText($this->privateKeyPem),
                InMemory::plainText($this->publicKeyPem),
            );

            $now = new \DateTimeImmutable;

            $userId = (string) $user->id()->value;
            assert($userId !== '');

            $token = $config->builder()
                ->withHeader('kid', 'v1')
                ->issuedBy($this->issuer)
                ->permittedFor($this->audience)
                ->identifiedBy(Uuid::uuid4()->toString())
                ->issuedAt($now)
                ->expiresAt($now->modify('+'.self::ACCESS_TOKEN_TTL_SECONDS.' seconds'))
                ->relatedTo($userId)
                ->withClaim('email', $user->email()->value)
                ->withClaim('roles', [])
                ->getToken($config->signer(), $config->signingKey());

            Log::info('jwt.issued', ['userId' => $user->id()->value, 'expiresIn' => self::ACCESS_TOKEN_TTL_SECONDS]);

            $refreshToken = $this->storeRefreshToken($user, $now);

            return new TokenPairDto(
                accessToken: $token->toString(),
                refreshToken: $refreshToken,
                expiresIn: self::ACCESS_TOKEN_TTL_SECONDS,
            );
        } catch (\Throwable $e) {
            Log::error('jwt.issue_failed', ['userId' => $user->id()->value, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function storeRefreshToken(User $user, \DateTimeImmutable $now): string
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);

        DB::table('auth.refresh_tokens')->insert([
            'user_id' => $user->id()->value,
            'token_hash' => $hash,
            'expires_at' => $now->modify('+'.self::REFRESH_TOKEN_TTL_SECONDS.' seconds'),
            'created_at' => $now,
        ]);

        return $token;
    }
}
