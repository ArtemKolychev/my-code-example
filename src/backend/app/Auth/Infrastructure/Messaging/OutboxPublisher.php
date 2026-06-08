<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Messaging;

use App\Auth\Application\Contract\OutboxPublisherInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class OutboxPublisher implements OutboxPublisherInterface
{
    /**
     * @throws \JsonException
     */
    public function publish(object $event): void
    {
        Log::debug('outbox.storing', ['event' => get_class($event)]);

        DB::table('auth.outbox_messages')->insert([
            'event_type' => get_class($event),
            'payload' => json_encode($event, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);

        Log::info('outbox.stored', ['event' => get_class($event)]);
    }
}
