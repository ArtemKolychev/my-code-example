<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Console;

use App\Auth\Infrastructure\Messaging\AmqpPublisher;
use App\Auth\Infrastructure\Messaging\RoutingKeyResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class PublishOutboxCommand extends Command
{
    protected $signature = 'outbox:publish-auth
                            {--batch=100 : Number of messages to process per iteration}
                            {--sleep=1 : Seconds to sleep when queue is empty}';

    protected $description = 'Relay auth outbox messages to RabbitMQ';

    private bool $running = true;

    public function __construct(
        private readonly AmqpPublisher $amqp,
        private readonly RoutingKeyResolver $resolver,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        pcntl_signal(SIGTERM, function (): void {
            $this->running = false;
        });
        pcntl_signal(SIGINT, function (): void {
            $this->running = false;
        });

        $batch = (int) $this->option('batch');
        $sleep = (int) $this->option('sleep');

        $this->info('Outbox relay started.');

        while ($this->running) {
            $published = DB::transaction(function () use ($batch): int {
                $messages = DB::select(
                    'SELECT id, event_type, payload, created_at
                     FROM outbox_messages
                     WHERE published_at IS NULL
                     ORDER BY created_at ASC
                     LIMIT ?
                     FOR UPDATE SKIP LOCKED',
                    [$batch],
                );

                /** @var array<int, object{ id: int|string, event_type: string, payload: array|string, created_at: string }> $messages */
                foreach ($messages as $message) {
                    $this->publishMessage($message);
                }

                return count($messages);
            });

            if ($published === 0) {
                sleep($sleep);
            }

            pcntl_signal_dispatch();
        }

        $this->info('Outbox relay stopped.');

        return self::SUCCESS;
    }

    /**
     * @param object{ id: int|string, event_type: string, payload: string|array, created_at: string } $message
     */
    private function publishMessage(object $message): void
    {
        $nowTs = time();
        $createdAt = new \DateTimeImmutable((string) $message->created_at);
        $queueTimeMs = ($nowTs - $createdAt->getTimestamp()) * 1000;

        $payload = is_string($message->payload)
            ? $message->payload
            : json_encode($message->payload, JSON_THROW_ON_ERROR);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($payload, associative: true, flags: JSON_THROW_ON_ERROR);

        $this->amqp->publish(
            exchange: 'core.events',
            routingKey: $this->resolver->resolve($message->event_type),
            payload: $payload,
            headers: [
                'event_type' => $message->event_type,
                'jobId' => (string) ($decoded['jobId'] ?? ''),
            ],
        );

        DB::statement(
            'UPDATE outbox_messages SET published_at = NOW() WHERE id = ?',
            [$message->id],
        );

        Log::info('outbox.processed', [
            'event_type' => $message->event_type,
            'queue_time_ms' => $queueTimeMs,
            'domain' => $this->extractDomain($message->event_type),
            'job_id' => (string) ($decoded['jobId'] ?? ''),
        ]);
    }

    private function extractDomain(string $eventType): string
    {
        // e.g. "App\Auth\Domain\User\Event\UserRegistered" -> "auth"
        $parts = explode('\\', $eventType);

        return isset($parts[1]) ? strtolower($parts[1]) : 'unknown';
    }
}
