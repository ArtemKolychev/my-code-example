<?php

declare(strict_types=1);

namespace App\Messenger;

use App\Message\ClickerEvent;
use LogicException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Deserializes raw JSON messages from the clicker (Node.js) into ClickerEvent envelopes.
 * Bypasses Symfony Messenger's PHP serialization envelope to allow cross-language messaging.
 */
class ClickerEventSerializer implements SerializerInterface
{
    public function decode(array $encodedEnvelope): Envelope
    {
        /** @var array<string, mixed> $body */
        $body = json_decode((string) $encodedEnvelope['body'], true, 512, JSON_THROW_ON_ERROR);

        $event = new ClickerEvent(
            type: (string) ($body['type'] ?? 'unknown'),
            jobId: (string) ($body['jobId'] ?? ''),
            step: isset($body['step']) ? (string) $body['step'] : null,
            error: isset($body['error']) ? (string) $body['error'] : null,
            inputType: isset($body['inputType']) ? (string) $body['inputType'] : null,
            inputPrompt: isset($body['inputPrompt']) ? (string) $body['inputPrompt'] : null,
            result: is_array($body['result'] ?? null) ? $body['result'] : [],
            articleId: isset($body['articleId']) ? (int) $body['articleId'] : null,
            imageUrls: isset($body['imageUrls']) && is_array($body['imageUrls']) ? array_map('strval', $body['imageUrls']) : null,
            fields: isset($body['fields']) && is_array($body['fields']) ? $body['fields'] : null,
            stepIndex: isset($body['stepIndex']) ? (int) $body['stepIndex'] : null,
            totalSteps: isset($body['totalSteps']) ? (int) $body['totalSteps'] : null,
            message: isset($body['message']) ? (string) $body['message'] : null,
        );

        return new Envelope($event);
    }

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
