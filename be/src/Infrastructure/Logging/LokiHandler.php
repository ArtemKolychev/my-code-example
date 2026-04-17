<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use Itspire\MonologLoki\Handler\LokiHandler as BaseLokiHandler;
use Monolog\Level;

/**
 * Pre-configured Loki postArticlesHandler registered as a service.
 * Uses LOKI_URL env var for the Loki endpoint.
 */
final class LokiHandler extends BaseLokiHandler
{
    public function __construct()
    {
        $rawLoki = $_ENV['LOKI_URL'] ?? getenv('LOKI_URL');
        $lokiUrl = rtrim(is_string($rawLoki) ? $rawLoki : 'http://loki:3100', '/');

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
