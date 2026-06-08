<?php

declare(strict_types=1);

namespace App\Auth\Domain\User\ValueObject;

final readonly class Name
{
    private function __construct(public readonly string $value) {}

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);
        if ($trimmed === '' || strlen($trimmed) > 255) {
            throw new \DomainException('Name must be between 1 and 255 characters.');
        }

        return new self($trimmed);
    }
}
