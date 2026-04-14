<?php

declare(strict_types=1);

namespace App\Adapter;

use App\Enum\Platform;

class SeznamPlatformFields implements PlatformFieldProvider
{
    public function getPlatform(): Platform
    {
        return Platform::Seznam;
    }

    public function getRequiredMetaFields(): array
    {
        return [];
    }
}
