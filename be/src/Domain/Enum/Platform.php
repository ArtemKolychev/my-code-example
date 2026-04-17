<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum Platform: string
{
    case Seznam = 'seznam';
    case Bazos = 'bazos';
    case Vinted = 'vinted';
    case MotoInzerce = 'motoinzerce';

    public function credentialService(): string
    {
        return match ($this) {
            self::Seznam => 'sbazar',
            self::Bazos => 'bazos',
            self::Vinted => 'vinted',
            self::MotoInzerce => 'motoinzerce',
        };
    }

    public function requiresCredential(): bool
    {
        return match ($this) {
            self::MotoInzerce => false,
            default => true,
        };
    }
}
