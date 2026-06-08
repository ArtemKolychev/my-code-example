<?php

declare(strict_types=1);

namespace App\Auth\Tests\Unit\Domain\ValueObject;

use App\Auth\Domain\User\ValueObject\HashedPassword;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HashedPasswordTest extends TestCase
{
    #[Test]
    public function from_plain_text_produces_bcrypt_hash(): void
    {
        $hp = HashedPassword::fromPlainText('secret123');

        self::assertTrue(str_starts_with($hp->hash, '$2y$'));
    }

    #[Test]
    public function from_hash_preserves_existing_hash(): void
    {
        $hash = password_hash('secret123', PASSWORD_BCRYPT);
        $hp = HashedPassword::fromHash($hash);

        self::assertSame($hash, $hp->hash);
    }

    #[Test]
    #[DataProvider('correct_passwords')]
    public function verify_returns_true_for_correct_password(string $plain): void
    {
        $hp = HashedPassword::fromPlainText($plain);

        self::assertTrue($hp->verify($plain));
    }

    #[Test]
    #[DataProvider('incorrect_passwords')]
    public function verify_returns_false_for_incorrect_password(string $plain, string $wrong): void
    {
        $hp = HashedPassword::fromPlainText($plain);

        self::assertFalse($hp->verify($wrong));
    }

    #[Test]
    public function two_hashes_of_same_plain_differ(): void
    {
        $a = HashedPassword::fromPlainText('same-password');
        $b = HashedPassword::fromPlainText('same-password');

        self::assertNotSame($a->hash, $b->hash);
    }

    public static function correct_passwords(): array
    {
        return [
            'simple' => ['secret123'],
            'with_symbols' => ['P@$$w0rd!'],
            'unicode' => ['пароль123'],
            'long' => [str_repeat('a', 72)],
        ];
    }

    public static function incorrect_passwords(): array
    {
        return [
            'case_differs' => ['Secret123', 'secret123'],
            'extra_space' => ['secret123', ' secret123'],
            'empty' => ['secret123', ''],
            'different' => ['correct-password', 'wrong-password'],
        ];
    }
}
