<?php

declare(strict_types=1);

namespace App\Application\DTO\Clicker;

interface ClickerPayloadInterface
{
    /** @return array<string, mixed> */
    public function toArray(): array;
}
