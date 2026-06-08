<?php

declare(strict_types=1);

namespace App\Auth\Tests\Feature;

use App\Auth\Tests\Fixtures\AuthTestSeeder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\TestCase;

final class LoginActionTest extends TestCase
{
    #[Test]
    public function returns_token_pair_for_valid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => AuthTestSeeder::ALICE_EMAIL,
            'password' => AuthTestSeeder::ALICE_PASSWORD,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'expires_in', 'token_type']]);
        $response->assertJsonPath('data.token_type', 'Bearer');
        $response->assertJsonPath('data.expires_in', 3600);
    }

    #[Test]
    public function returns_401_for_wrong_password(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => AuthTestSeeder::ALICE_EMAIL,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
        $response->assertJsonFragment(['message' => 'Invalid credentials.']);
    }

    #[Test]
    public function returns_401_for_unknown_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'irrelevant',
        ]);

        $response->assertStatus(401);
        $response->assertJsonFragment(['message' => 'Invalid credentials.']);
    }

    #[Test]
    #[DataProvider('invalid_login_payloads')]
    public function returns_422_on_invalid_payload(array $payload, string $field): void
    {
        $response = $this->postJson('/api/auth/login', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([$field]);
    }

    public static function invalid_login_payloads(): array
    {
        $base = ['email' => 'user@example.com', 'password' => 'secret123'];

        return [
            'missing_email' => [['password' => 'secret123'], 'email'],
            'invalid_email_format' => [array_merge($base, ['email' => 'not-an-email']), 'email'],
            'missing_password' => [['email' => 'user@example.com'], 'password'],
        ];
    }
}
