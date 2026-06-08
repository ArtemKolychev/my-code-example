<?php

declare(strict_types=1);

namespace App\Auth\Tests\Feature;

use App\Auth\Tests\Fixtures\AuthTestSeeder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\TestCase;

final class RegisterActionTest extends TestCase
{
    #[Test]
    public function registers_new_user_and_returns_202_with_job_id(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Charlie',
            'email' => 'charlie@example.com',
            'password' => 'Secret1234!',
            'password_confirmation' => 'Secret1234!',
        ]);

        $response->assertStatus(202);
        $response->assertJsonStructure(['jobId']);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $response->json('jobId'),
        );
    }

    #[Test]
    public function returns_422_on_duplicate_email(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Alice2',
            'email' => AuthTestSeeder::ALICE_EMAIL,
            'password' => 'Secret1234!',
            'password_confirmation' => 'Secret1234!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'User with email '.AuthTestSeeder::ALICE_EMAIL.' already exists.']);
    }

    #[Test]
    #[DataProvider('invalid_register_payloads')]
    public function returns_422_on_invalid_payload(array $payload, string $field): void
    {
        $response = $this->postJson('/api/auth/register', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([$field]);
    }

    public static function invalid_register_payloads(): array
    {
        $base = [
            'name' => 'Valid Name',
            'email' => 'valid@example.com',
            'password' => 'Secret1234!',
            'password_confirmation' => 'Secret1234!',
        ];

        return [
            'missing_name' => [array_merge($base, ['name' => '']), 'name'],
            'missing_email' => [array_merge($base, ['email' => '']), 'email'],
            'invalid_email_format' => [array_merge($base, ['email' => 'not-an-email']), 'email'],
            'short_password' => [array_merge($base, ['password' => '1234567', 'password_confirmation' => '1234567']), 'password'],
            'password_mismatch' => [array_merge($base, ['password_confirmation' => 'different']), 'password'],
            'missing_confirmation' => [array_merge($base, ['password_confirmation' => '']), 'password_confirmation'],
        ];
    }
}
