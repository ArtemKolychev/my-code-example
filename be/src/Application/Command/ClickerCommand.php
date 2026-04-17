<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Application\DTO\Clicker\ClickerPayloadInterface;

/**
 * A command to be sent to clicker/ai-agent services via AMQP.
 * Wraps a routing key and a typed payload DTO that will be serialized as JSON.
 */
final readonly class ClickerCommand
{
    public function __construct(
        private string $routingKey,
        private ClickerPayloadInterface $payload,
    ) {
    }

    public function getRoutingKey(): string
    {
        return $this->routingKey;
    }

    public function getPayload(): ClickerPayloadInterface
    {
        return $this->payload;
    }
}
