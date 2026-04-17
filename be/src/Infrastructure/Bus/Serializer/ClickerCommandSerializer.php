<?php

declare(strict_types=1);

namespace App\Infrastructure\Bus\Serializer;

use App\Application\Command\ClickerCommand;
use LogicException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Serializes ClickerCommand messages as raw JSON for Node.js clicker/ai-agent consumption.
 * Decode is not supported — this transport is publish-only.
 */
class ClickerCommandSerializer implements SerializerInterface
{
    /** @param array<string, mixed> $encodedEnvelope */
    public function decode(array $encodedEnvelope): Envelope
    {
        throw new LogicException('ClickerCommandSerializer does not support decoding. This transport is publish-only.');
    }

    /** @return array<string, mixed> */
    public function encode(Envelope $envelope): array
    {
        $message = $envelope->getMessage();
        if (!$message instanceof ClickerCommand) {
            throw new LogicException('ClickerCommandSerializer can only encode ClickerCommand messages.');
        }

        return [
            'body' => json_encode($message->getPayload()->toArray(), JSON_THROW_ON_ERROR),
            'headers' => [
                'Content-Type' => 'application/json',
                'type' => ClickerCommand::class,
            ],
        ];
    }
}
