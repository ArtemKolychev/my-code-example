<?php

declare(strict_types=1);

namespace App\Logging;

use Itspire\MonologLoki\Handler\LokiHandler as BaseLokiHandler;
use Monolog\Level;

/**
 * Pre-configured Loki handler registered as a service.
 * Uses LOKI_URL env var for the Loki endpoint.
 */
final class LokiHandler extends BaseLokiHandler
{
    public function __construct()
    {
        $lokiUrl = rtrim((string) ($_ENV['LOKI_URL'] ?? getenv('LOKI_URL') ?: 'http://loki:3100'), '/');

        parent::__construct(
            apiConfig: [
                'entrypoint' => $lokiUrl,
                'labels' => ['service' => 'php'],
                'context' => [],
            ],
            level: Level::Debug,
            bubble: true,
        );
    }
}
