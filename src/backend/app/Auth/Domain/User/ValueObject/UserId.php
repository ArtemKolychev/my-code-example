<?php

declare(strict_types=1);

namespace App\Auth\Domain\User\ValueObject;

use Ramsey\Uuid\Uuid;

final readonly class UserId
{
    private function __construct(public readonly string $value) {}

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }
}
