<?php

declare(strict_types=1);

namespace App\Messenger;

use App\Message\ClickerCommand;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Automatically adds an AmqpStamp with the routing key from ClickerCommand messages.
 */
class ClickerCommandMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();

        if ($message instanceof ClickerCommand && null === $envelope->last(AmqpStamp::class)) {
            $envelope = $envelope->with(new AmqpStamp($message->getRoutingKey()));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
