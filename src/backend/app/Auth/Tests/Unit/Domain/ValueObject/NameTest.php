<?php

declare(strict_types=1);

namespace App\Auth\Tests\Unit\Domain\ValueObject;

use App\Auth\Domain\User\ValueObject\Name;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NameTest extends TestCase
{
    #[Test]
    #[DataProvider('valid_names')]
    public function accepts_valid_name(string $input, string $expected): void
    {
        $vo = Name::fromString($input);

        self::assertSame($expected, $vo->value);
    }

    #[Test]
    #[DataProvider('invalid_names')]
    public function rejects_invalid_name(string $name): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/Name must be between/');

        Name::fromString($name);
    }

    #[Test]
    public function trims_surrounding_whitespace(): void
    {
        $vo = Name::fromString('  Alice  ');

        self::assertSame('Alice', $vo->value);
    }

    public static function valid_names(): array
    {
        return [
            'simple' => ['Alice', 'Alice'],
            'with_space' => ['Alice Smith', 'Alice Smith'],
            'unicode' => ['Алексей', 'Алексей'],
            'with_trim' => ['  Bob  ', 'Bob'],
            'single_char' => ['A', 'A'],
            'max_length' => [str_repeat('a', 255), str_repeat('a', 255)],
        ];
    }

    public static function invalid_names(): array
    {
        return [
            'empty_string' => [''],
            'whitespace_only' => ['   '],
            'too_long' => [str_repeat('a', 256)],
        ];
    }
}
