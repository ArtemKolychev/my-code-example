<?php

declare(strict_types=1);

namespace App\Auth\Tests\Unit\Domain\ValueObject;

use App\Auth\Domain\User\ValueObject\UserId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserIdTest extends TestCase
{
    private const string VALID_UUID = 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11';

    #[Test]
    public function generate_produces_valid_uuid_v4_format(): void
    {
        $id = UserId::generate();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id->value,
        );
    }

    #[Test]
    public function generate_produces_unique_ids(): void
    {
        $ids = array_map(fn () => UserId::generate()->value, range(1, 5));

        self::assertCount(5, array_unique($ids));
    }

    #[Test]
    public function from_string_preserves_value(): void
    {
        $id = UserId::fromString(self::VALID_UUID);

        self::assertSame(self::VALID_UUID, $id->value);
    }

    #[Test]
    public function from_string_round_trips_through_generate(): void
    {
        $generated = UserId::generate();
        $restored = UserId::fromString($generated->value);

        self::assertSame($generated->value, $restored->value);
    }
}
