<?php

declare(strict_types=1);

namespace App\Tests\Shared\Mother;

use App\Domain\Entity\ServiceCredential;

/**
 * Object Mother for ServiceCredential domain entities.
 *
 * Returns anonymous subclasses of ServiceCredential that override only
 * getDecryptedPassword() — the one method that depends on the sodium extension.
 * All other behaviour (getLogin, getService, setters) comes from the real class.
 */
final class CredentialMother
{
    /**
     * A credential for the given platform service key (e.g. 'sbazar', 'bazos').
     * getDecryptedPassword() returns a fixed plain-text string without touching sodium.
     */
    public static function forService(string $service = 'sbazar'): ServiceCredential
    {
        $credential = new class extends ServiceCredential {
            public function getDecryptedPassword(string $appSecret): string
            {
                return 's3cr3t';
            }
        };

        $credential->setService($service);
        $credential->setLogin('user@example.com');

        return $credential;
    }

    /**
     * Convenience alias — use when the specific service name does not matter.
     */
    public static function any(): ServiceCredential
    {
        return self::forService();
    }
}
