<?php

declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

// Force test environment variables, overriding any Docker/system env vars.
// This ensures feature tests always connect to the test database.
$testEnv = [
    'APP_ENV' => 'testing',
    'DB_DATABASE' => 'core_test',
    'CACHE_DRIVER' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'JWT_PRIVATE_KEY_PATH' => __DIR__.'/Fixtures/keys/private.pem',
    'JWT_PUBLIC_KEY_PATH' => __DIR__.'/Fixtures/keys/public.pem',
];

foreach ($testEnv as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
