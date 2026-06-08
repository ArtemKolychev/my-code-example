<?php

declare(strict_types=1);

namespace App\Auth\Domain\User\ValueObject;

final readonly class HashedPassword
{
    private function __construct(public readonly string $hash) {}

    public static function fromPlainText(string $plainText): self
    {
        return new self((string) password_hash($plainText, PASSWORD_BCRYPT));
    }

    public static function fromHash(string $hash): self
    {
        return new self($hash);
    }

    public function verify(string $plainText): bool
    {
        return password_verify($plainText, $this->hash);
    }
}
