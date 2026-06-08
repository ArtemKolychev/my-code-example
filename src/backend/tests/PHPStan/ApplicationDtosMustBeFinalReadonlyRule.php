<?php

declare(strict_types=1);

namespace Tests\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/** @implements Rule<Class_> */
final class ApplicationDtosMustBeFinalReadonlyRule implements Rule
{
    private const array PROTECTED_NAMESPACES = [
        'App\\Application\\DTO\\Request\\',
        'App\\Application\\DTO\\Response\\',
    ];

    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $className = $scope->getClassReflection()?->getName() ?? '';

        if (! $this->isInProtectedNamespace($className)) {
            return [];
        }

        if ($node->isFinal() && $node->isReadonly()) {
            return [];
        }

        $missing = [];
        if (! $node->isFinal()) {
            $missing[] = 'final';
        }
        if (! $node->isReadonly()) {
            $missing[] = 'readonly';
        }

        return [
            RuleErrorBuilder::message(
                sprintf(
                    'Application DTO %s must be declared as "final readonly class". Missing: %s. '
                    .'Request and Response DTOs are immutable data carriers.',
                    $className,
                    implode(', ', $missing),
                )
            )
                ->identifier('core.applicationDtoMustBeFinalReadonly')
                ->build(),
        ];
    }

    private function isInProtectedNamespace(string $className): bool
    {
        return array_any(
            self::PROTECTED_NAMESPACES,
            fn ($ns): bool => str_starts_with($className, (string) $ns),
        );
    }
}
