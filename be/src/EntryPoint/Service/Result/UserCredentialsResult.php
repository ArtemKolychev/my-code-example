<?php

declare(strict_types=1);

namespace App\EntryPoint\Service\Result;

use Symfony\Component\Form\FormInterface;

/** Result VO returned by UserCredentialsFormHandler. */
final readonly class UserCredentialsResult
{
    /**
     * @param array<string, FormInterface<array<string, mixed>>> $credentialForms
     * @param array<string, bool>                                $hasCredential
     */
    public function __construct(
        public bool $redirectNeeded,
        public array $credentialForms,
        public array $hasCredential,
        public ?string $savedService = null,
    ) {
    }
}
