<?php

declare(strict_types=1);

namespace App\Application\DTO\Payload;

use App\Domain\Entity\User;

/** Mutable DTO for the user profile form. */
class UserProfilePayload
{
    public ?string $name = null;
    public ?string $address = null;
    public ?string $zip = null;
    public ?string $phone = null;

    public static function fromUser(User $user): self
    {
        $dto = new self();
        $dto->name = $user->getName();
        $dto->address = $user->getAddress();
        $dto->zip = $user->getZip();
        $dto->phone = $user->getPhone();

        return $dto;
    }
}
