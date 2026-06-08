<?php

declare(strict_types=1);

namespace App\Auth\Tests\Feature;

use App\Auth\Tests\Fixtures\AuthTestSeeder;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\TestCase;

final class RefreshActionTest extends TestCase
{
    #[Test]
    public function returns_new_token_pair_for_valid_refresh_token(): void
    {
        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => AuthTestSeeder::VALID_REFRESH_TOKEN,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'expires_in', 'token_type']]);
        $response->assertJsonPath('data.token_type', 'Bearer');
    }

    #[Test]
    public function revokes_old_token_after_refresh(): void
    {
        $this->postJson('/api/auth/refresh', [
            'refresh_token' => AuthTestSeeder::VALID_REFRESH_TOKEN,
        ])->assertStatus(200);

        $second = $this->postJson('/api/auth/refresh', [
            'refresh_token' => AuthTestSeeder::VALID_REFRESH_TOKEN,
        ]);

        $second->assertStatus(401);
        $second->assertJsonFragment(['message' => 'Invalid or expired refresh token.']);
    }

    #[Test]
    public function returns_401_for_expired_token(): void
    {
        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => AuthTestSeeder::EXPIRED_REFRESH_TOKEN,
        ]);

        $response->assertStatus(401);
        $response->assertJsonFragment(['message' => 'Invalid or expired refresh token.']);
    }

    #[Test]
    public function returns_401_for_revoked_token(): void
    {
        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => AuthTestSeeder::REVOKED_REFRESH_TOKEN,
        ]);

        $response->assertStatus(401);
        $response->assertJsonFragment(['message' => 'Invalid or expired refresh token.']);
    }

    #[Test]
    public function returns_401_for_unknown_token(): void
    {
        $response = $this->postJson('/api/auth/refresh', [
            'refresh_token' => 'completely-unknown-token-that-does-not-exist',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function returns_422_on_missing_refresh_token(): void
    {
        $response = $this->postJson('/api/auth/refresh', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['refresh_token']);
    }
}
