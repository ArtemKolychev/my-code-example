<?php

declare(strict_types=1);

namespace Tests\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/** @implements Rule<Class_> */
final class HandlersMustBeReadonlyRule implements Rule
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $className = $scope->getClassReflection()?->getName() ?? '';

        if (! str_starts_with($className, 'App\\Application\\Handler\\')) {
            return [];
        }

        if ($node->isReadonly()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                sprintf(
                    'Handler %s must be declared as "final readonly class". '
                    .'Handlers have only immutable constructor-injected dependencies.',
                    $className,
                )
            )
                ->identifier('core.handlerMustBeReadonly')
                ->build(),
        ];
    }
}
