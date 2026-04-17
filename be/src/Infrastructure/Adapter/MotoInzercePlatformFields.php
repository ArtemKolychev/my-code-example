<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter;

use App\Domain\Enum\Platform;
use App\Domain\Port\PlatformFieldProviderInterface;

class MotoInzercePlatformFields implements PlatformFieldProviderInterface
{
    public function getPlatform(): Platform
    {
        return Platform::MotoInzerce;
    }

    /** @return array<string, array{label: string, type: string}> */
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
