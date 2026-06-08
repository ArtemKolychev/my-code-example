<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Messaging;

use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

final readonly class AmqpPublisher
{
    public function __construct(
        private string $host,
        private int $port,
        private string $user,
        private string $password,
    ) {}

    /** @param array<string, string> $headers */
    public function publish(string $exchange, string $routingKey, string $payload, array $headers = []): void
    {
        Log::debug('amqp.publishing', ['exchange' => $exchange, 'routingKey' => $routingKey]);

        try {
            $connection = new AMQPStreamConnection($this->host, $this->port, $this->user, $this->password);
            $channel = $connection->channel();

            $message = new AMQPMessage($payload, [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'content_type' => 'application/json',
                'application_headers' => new AMQPTable($headers),
            ]);

            $channel->basic_publish($message, $exchange, $routingKey);

            $channel->close();
            $connection->close();

            Log::info('amqp.published', ['exchange' => $exchange, 'routingKey' => $routingKey]);
        } catch (\Throwable $e) {
            Log::error('amqp.failed', ['exchange' => $exchange, 'routingKey' => $routingKey, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
