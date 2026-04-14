<?php

declare(strict_types=1);

namespace App\Adapter;

use App\Enum\Platform;

class VintedPlatformFields implements PlatformFieldProvider
{
    public function getPlatform(): Platform
    {
        return Platform::Vinted;
    }

    public function getRequiredMetaFields(): array
    {
        return [
            'brand' => ['label' => 'Značka', 'type' => 'text'],
            'size' => ['label' => 'Velikost', 'type' => 'text'],
        ];
    }
}
