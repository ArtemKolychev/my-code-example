<?php

declare(strict_types=1);

namespace App\Infrastructure\Bus\Serializer;

use App\Domain\Event\ClickerEvent;
use LogicException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Deserializes raw JSON messages from the clicker (Node.js) into ClickerEvent envelopes.
 * Bypasses Symfony Messenger's PHP serialization envelope to allow cross-language messaging.
 */
class ClickerEventSerializer implements SerializerInterface
{
    /** @param array<string, mixed> $encodedEnvelope */
    public function decode(array $encodedEnvelope): Envelope
    {
        /** @var string $bodyJson */
        $bodyJson = $encodedEnvelope['body'];

        /**
         * @var array{
         *     type?: string,
         *     jobId?: string,
         *     step?: string,
         *     error?: string,
         *     inputType?: string,
         *     inputPrompt?: string,
         *     result?: array<string, mixed>,
         *     articleId?: int,
         *     imageUrls?: list<string>,
         *     fields?: list<array<string, mixed>>,
         *     stepIndex?: int,
         *     totalSteps?: int,
         *     message?: string,
         * } $body
         */
        $body = json_decode($bodyJson, true, 512, JSON_THROW_ON_ERROR);

        $event = new ClickerEvent(
            type: $body['type'] ?? 'unknown',
            jobId: $body['jobId'] ?? '',
            step: $body['step'] ?? null,
            error: $body['error'] ?? null,
            inputType: $body['inputType'] ?? null,
            inputPrompt: $body['inputPrompt'] ?? null,
            result: $body['result'] ?? [],
            articleId: $body['articleId'] ?? null,
            imageUrls: $body['imageUrls'] ?? null,
            fields: $body['fields'] ?? null,
            stepIndex: $body['stepIndex'] ?? null,
            totalSteps: $body['totalSteps'] ?? null,
            message: $body['message'] ?? null,
        );

        return new Envelope($event);
    }

    /** @return array<string, mixed> */
    public function encode(Envelope $envelope): array
    {
        $message = $envelope->getMessage();
        if (!$message instanceof ClickerEvent) {
            throw new LogicException('ClickerEventSerializer can only encode ClickerEvent messages.');
        }

        return [
            'body' => json_encode([
                'type' => $message->getType(),
                'jobId' => $message->getJobId(),
                'step' => $message->getStep(),
                'error' => $message->getError(),
                'inputType' => $message->getInputType(),
                'inputPrompt' => $message->getInputPrompt(),
                'result' => $message->getResult(),
            ], JSON_THROW_ON_ERROR),
            'headers' => ['Content-Type' => 'application/json'],
        ];
    }
}
