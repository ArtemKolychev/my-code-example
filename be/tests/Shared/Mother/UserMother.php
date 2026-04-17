<?php

declare(strict_types=1);

namespace App\Tests\Shared\Mother;

use App\Domain\Entity\ServiceCredential;
use App\Domain\Entity\User;

/**
 * Object Mother for User domain entities.
 *
 * Returns anonymous subclasses of User that override getCredentialForService()
 * — the one method backed by a private Doctrine Collection.
 * This avoids both the real ORM collection and PHPUnit mock boilerplate in every test.
 */
final class UserMother
{
    /**
     * A user that returns $credential for every platform service lookup.
     * Defaults to CredentialMother::any() when no credential is provided.
     */
    public static function withCredential(?ServiceCredential $credential = null): User
    {
        $resolved = $credential ?? CredentialMother::any();

        return new class($resolved) extends User {
            public function __construct(private readonly ServiceCredential $mockedCred)
            {
                parent::__construct();
                $this->id = random_int(1, 1000);
                $this->email = 'user@example.com';
                $this->name = 'Test User';
            }

            public function getCredentialForService(string $service): ServiceCredential
            {
                return $this->mockedCred;
            }
        };
    }

    /**
     * A user that has no credential for any service.
     * Use this to test MissingCredentialException paths.
     */
    public static function withoutCredential(): User
    {
        return new class extends User {
            public function __construct()
            {
                parent::__construct();
                $this->id = random_int(1, 1000);
                $this->email = 'user@example.com';
            }

            public function getCredentialForService(string $service): ?ServiceCredential
            {
                return null;
            }
        };
    }

    /**
     * A plain real User instance with just an email set.
     * Use in unit tests that don't need credential lookup (e.g. ForgotPasswordServiceTest)
     * and in functional tests where the entity will be persisted to the DB.
     * The caller is responsible for hashing the password and persisting when needed.
     */
    public static function withEmail(string $email): User
    {
        return new User()->setEmail($email);
    }

    /**
     * A user that returns a credential only when asked for a specific service key,
     * and null for all other services.
     *
     * Use this to test platform-detection logic that must look up the correct service key.
     */
    public static function withCredentialForService(string $targetService, ServiceCredential $credential): User
    {
        return new class($targetService, $credential) extends User {
            public function __construct(
                private readonly string $targetService,
                private readonly ServiceCredential $mockedCred,
            ) {
                parent::__construct();
                $this->id = random_int(1, 1000);
                $this->email = 'user@example.com';
            }

            public function getCredentialForService(string $service): ?ServiceCredential
            {
                return $service === $this->targetService ? $this->mockedCred : null;
            }
        };
    }
}
