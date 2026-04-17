<?php

declare(strict_types=1);

namespace App\Infrastructure\Bus\Middleware;

use App\Application\Logging\TraceContext;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

class TraceContextMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        TraceContext::setTraceId(TraceContext::generateTraceId());

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            TraceContext::clear();
        }
    }
}
