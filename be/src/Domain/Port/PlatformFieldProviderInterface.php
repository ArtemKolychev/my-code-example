<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\Enum\Platform;

interface PlatformFieldProviderInterface
{
    public function getPlatform(): Platform;

    /** @return array<string, array{label: string, type: string}> */
    public function getRequiredMetaFields(): array;
}
