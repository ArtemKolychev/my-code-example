<?php

declare(strict_types=1);

namespace App\Adapter;

use App\Enum\Platform;

class MotoInzercePlatformFields implements PlatformFieldProvider
{
    public function getPlatform(): Platform
    {
        return Platform::MotoInzerce;
    }

    public function getRequiredMetaFields(): array
    {
        return [
            'brand' => ['label' => 'Značka motocyklu', 'type' => 'text'],
            'model' => ['label' => 'Model motocyklu', 'type' => 'text'],
            'year' => ['label' => 'Rok výroby', 'type' => 'number'],
            'displacement_cc' => ['label' => 'Objem motoru (cm³)', 'type' => 'number'],
        ];
    }
}
