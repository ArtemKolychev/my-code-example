<?php

declare(strict_types=1);

namespace App\Adapter;

use App\Enum\Platform;

interface PlatformFieldProvider
{
    public function getPlatform(): Platform;

    /** @return array<string, array{label: string, type: string}> */
    public function getRequiredMetaFields(): array;
}
