<?php

declare(strict_types=1);

namespace App\Adapter;

use App\Enum\Platform;

class BazosPlatformFields implements PlatformFieldProvider
{
    public function getPlatform(): Platform
    {
        return Platform::Bazos;
    }

    public function getRequiredMetaFields(): array
    {
        return [];
    }
}
