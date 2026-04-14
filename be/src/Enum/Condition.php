<?php

declare(strict_types=1);

namespace App\Enum;

enum Condition: string
{
    case New = 'new';
    case LikeNew = 'like_new';
    case VeryGood = 'very_good';
    case Good = 'good';
    case Acceptable = 'acceptable';
    case NeedsRepair = 'needs_repair';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nové',
            self::LikeNew => 'Jako nové',
            self::VeryGood => 'Velmi dobrý stav',
            self::Good => 'Dobrý stav',
            self::Acceptable => 'Uspokojivý stav',
            self::NeedsRepair => 'Potřebuje opravu',
        };
    }
}
