<?php

declare(strict_types=1);

namespace Tests\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/** @implements Rule<Property> */
final class NoMutableStaticPropertiesRule implements Rule
{
    public function getNodeType(): string
    {
        return Property::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->isStatic() || $node->isReadonly()) {
            return [];
        }

        $className = $scope->getClassReflection()?->getName() ?? '';

        if (! str_starts_with($className, 'App\\')) {
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
                ->identifier('core.noMutableStaticProperties')
                ->build(),
        ];
    }
}
