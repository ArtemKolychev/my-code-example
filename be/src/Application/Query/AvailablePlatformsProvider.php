<?php

declare(strict_types=1);

namespace App\Application\Query;

use App\Domain\Entity\User;
use App\Domain\Enum\Platform;

final readonly class AvailablePlatformsProvider
{
    /** @return Platform[] */
    public function getAvailablePlatforms(User $user): array
    {
        return array_values(array_filter(
            Platform::cases(),
            fn (Platform $p): bool => $this->isPublishable($p, $user),
        ));
    }

    private function isPublishable(Platform $platform, User $user): bool
    {
        if (Platform::Vinted === $platform) {
            return false;
        }

        return (bool) $user->getCredentialForService($platform->credentialService());
    }
}
