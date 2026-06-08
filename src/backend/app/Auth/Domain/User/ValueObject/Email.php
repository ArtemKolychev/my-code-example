<?php

declare(strict_types=1);

namespace App\Auth\Domain\User\ValueObject;

final readonly class Email
{
    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \DomainException("Invalid email address: {$value}");
        }

        return new self(strtolower($value));
    }
}
