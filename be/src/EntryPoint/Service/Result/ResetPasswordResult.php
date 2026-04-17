<?php

declare(strict_types=1);

namespace App\EntryPoint\Service\Result;

use App\Application\DTO\Payload\ResetPasswordPayload;
use Symfony\Component\Form\FormInterface;

final readonly class ResetPasswordResult
{
    /**
     * @param FormInterface<ResetPasswordPayload>|null $form
     */
    public function __construct(
        public bool $dispatched,
        public ?FormInterface $form = null,
    ) {
    }
}
