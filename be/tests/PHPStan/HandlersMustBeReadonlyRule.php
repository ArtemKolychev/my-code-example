<?php

declare(strict_types=1);

namespace App\Tests\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Enforces that all Application Handlers are final readonly classes.
 *
 * Handlers have only immutable constructor-injected dependencies.
 * Marking them readonly prevents accidental property mutation after construction.
 *
 * ❌  final class PublishArticleHandler { ... }
 * ✅  final readonly class PublishArticleHandler { ... }
 *
 * @implements Rule<Class_>
 */
final class HandlersMustBeReadonlyRule implements Rule
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $className = $scope->getClassReflection()?->getName() ?? '';

        if (!str_starts_with($className, 'App\\Application\\Handler\\')) {
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
                ->identifier('bazar.handlerMustBeReadonly')
                ->build(),
        ];
    }
}
