<?php

declare(strict_types=1);

namespace App\Application\DTO\Clicker;

final readonly class CredentialData
{
    public function __construct(
        public string $login,
        public string $password,
    ) {
    }

    /** @return array{login: string, password: string} */
    public function toArray(): array
    {
        return ['login' => $this->login, 'password' => $this->password];
    }
}
