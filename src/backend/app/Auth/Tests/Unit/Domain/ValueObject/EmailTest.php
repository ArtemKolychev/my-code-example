<?php

declare(strict_types=1);

namespace App\Auth\Tests\Unit\Domain\ValueObject;

use App\Auth\Domain\User\ValueObject\Email;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    #[Test]
    #[DataProvider('valid_emails')]
    public function accepts_valid_email(string $email): void
    {
        $vo = Email::fromString($email);

        self::assertSame(strtolower($email), $vo->value);
    }

    #[Test]
    #[DataProvider('invalid_emails')]
    public function rejects_invalid_email(string $email): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Invalid email address/');

        Email::fromString($email);
    }

    #[Test]
    public function normalizes_to_lowercase(): void
    {
        $vo = Email::fromString('User@EXAMPLE.COM');

        self::assertSame('user@example.com', $vo->value);
    }

    public static function valid_emails(): array
    {
        return [
            'simple' => ['user@example.com'],
            'subdomain' => ['user@mail.example.com'],
            'plus_alias' => ['user+tag@example.com'],
            'uppercase_input' => ['User@Example.COM'],
            'digits_in_local' => ['user123@example.org'],
            'hyphen_in_domain' => ['user@my-domain.com'],
        ];
    }

    public static function invalid_emails(): array
    {
        return [
            'no_at' => ['not-an-email'],
            'no_domain' => ['user@'],
            'empty' => [''],
            'only_at' => ['@'],
            'spaces' => ['user @example.com'],
            'double_at' => ['user@@example.com'],
            'no_tld' => ['user@localhost'],
        ];
    }
}
