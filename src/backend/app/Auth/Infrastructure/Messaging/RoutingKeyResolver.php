<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Messaging;

final readonly class RoutingKeyResolver
{
    /** @param class-string $eventClass */
    public function resolve(string $eventClass): string
    {
        $shortName = (new \ReflectionClass($eventClass))->getShortName();
        $snakeCase = (string) preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName);

        return 'core.events.auth.'.strtolower($snakeCase).'.v1';
    }
}
