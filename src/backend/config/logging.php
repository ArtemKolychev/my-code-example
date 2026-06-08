<?php

declare(strict_types=1);

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use OpenTelemetry\Contrib\Logs\Monolog\Handler;

return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['stderr', 'otel'],
            'ignore_exceptions' => false,
        ],

        'stderr' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'formatter' => JsonFormatter::class,
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'otel' => [
            'driver' => 'monolog',
            'handler' => Handler::class,
            'level' => env('LOG_LEVEL', 'debug'),
        ],
    ],
];
