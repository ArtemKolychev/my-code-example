<?php

declare(strict_types=1);

namespace App\Message;

/**
 * A command to be sent to clicker/ai-agent services via AMQP.
 * Wraps a routing key and raw payload that will be serialized as JSON.
 */
class ClickerCommand
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly string $routingKey,
        private readonly array $payload,
    ) {
    }

    public function getRoutingKey(): string
    {
        return $this->routingKey;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }
}
