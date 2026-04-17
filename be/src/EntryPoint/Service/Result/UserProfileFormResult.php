<?php

declare(strict_types=1);

namespace App\EntryPoint\Service\Result;

use App\Application\DTO\Payload\UserProfilePayload;
use Symfony\Component\Form\FormInterface;

/** Result VO returned by UserProfileFormHandler. */
final readonly class UserProfileFormResult
{
    /**
     * @param FormInterface<UserProfilePayload> $profileForm
     */
    public function __construct(
        public bool $redirectNeeded,
        public FormInterface $profileForm,
    ) {
    }
}
