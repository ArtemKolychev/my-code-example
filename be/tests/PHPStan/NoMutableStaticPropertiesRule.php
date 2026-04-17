<?php

declare(strict_types=1);

namespace App\Tests\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Forbids mutable static properties in all App\ classes.
 *
 * Static properties create hidden global state that breaks testability
 * and violates Dependency Injection principles. Use constructor-injected
 * services or enums with pure methods instead.
 *
 * Allowed: static readonly (effectively a constant), static const.
 *
 * @implements Rule<Property>
 */
final class NoMutableStaticPropertiesRule implements Rule
{
    public function getNodeType(): string
    {
        return Property::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->isStatic()) {
            return [];
        }

        // static readonly is fine — it behaves like a constant
        if ($node->isReadonly()) {
            return [];
        }

        $className = $scope->getClassReflection()?->getName() ?? '';

        if (!str_starts_with($className, 'App\\')) {
            return [];
        }

        // Exempt legitimate static context holders (e.g., distributed tracing MDC-style context)
        if (str_starts_with($className, 'App\\Application\\Logging\\')) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                sprintf(
                    'Class %s must not have mutable static properties. '
                    .'Use constructor injection, enums, or static readonly instead.',
                    $className,
                )
            )
                ->identifier('bazar.noMutableStaticProperties')
                ->build(),
        ];
    }
}
