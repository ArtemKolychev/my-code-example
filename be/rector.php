<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Php82\Rector\Class_\ReadOnlyClassRector;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SymfonySetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/src/Kernel.php',
        // Doctrine entities and repositories CANNOT be readonly or final — proxy classes extend them
        ReadOnlyClassRector::class => [
            __DIR__.'/src/Domain/Entity',
            __DIR__.'/src/Infrastructure/Persistence/Doctrine',
            // Payload DTOs must stay mutable — Symfony PropertyAccessor needs to write to them
            __DIR__.'/src/Application/DTO/Payload',
        ],
        ReadOnlyPropertyRector::class => [
            __DIR__.'/src/Domain/Entity',
            __DIR__.'/src/Infrastructure/Persistence/Doctrine',
            // Payload DTOs must stay mutable — Symfony PropertyAccessor needs to write to them
            __DIR__.'/src/Application/DTO/Payload',
        ],
    ])
    ->withSets([
        SetList::PHP_82,
        SetList::PHP_83,
        SetList::PHP_84,
        SetList::TYPE_DECLARATION,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        SetList::PRIVATIZATION,
        SymfonySetList::SYMFONY_CODE_QUALITY,
    ])
    // Auto-detects installed Symfony version from composer.json and applies the right migration sets
    ->withComposerBased(symfony: true)
    ->withPhpSets();
