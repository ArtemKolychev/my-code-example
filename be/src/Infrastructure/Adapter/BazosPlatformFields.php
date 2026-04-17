<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter;

use App\Domain\Enum\Platform;
use App\Domain\Port\PlatformFieldProviderInterface;

class BazosPlatformFields implements PlatformFieldProviderInterface
{
    public function getPlatform(): Platform
    {
        return Platform::Bazos;
    }

    /** @return array<string, array{label: string, type: string}> */
    public function getRequiredMetaFields(): array
    {
        return [];
    }
}
