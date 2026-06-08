<?php

declare(strict_types=1);

namespace App\Auth\Application\Contract;

interface OutboxPublisherInterface
{
    public function publish(object $event): void;
}
