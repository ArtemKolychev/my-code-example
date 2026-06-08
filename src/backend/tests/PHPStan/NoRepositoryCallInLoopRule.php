<?php

declare(strict_types=1);

namespace Tests\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/** @implements Rule<Foreach_> */
final class NoRepositoryCallInLoopRule implements Rule
{
    /** @var list<string> */
    private const array WRITE_PREFIXES = ['save', 'persist', 'delete', 'remove', 'flush', 'update', 'insert'];

    public function getNodeType(): string
    {
        return Foreach_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        foreach ($this->findRepositoryMethodCalls($node->stmts) as $call) {
            $methodName = $call->name instanceof Identifier ? $call->name->name : null;
            if ($methodName === null || $this->isWriteMethod($methodName)) {
                continue;
            }

            $className = $this->resolveRepositoryClass($call, $scope);
            if ($className === null) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(
                sprintf(
                    'Repository method %s::%s() must not be called inside a loop — N+1 queries. '
                    .'Use a batch method and group results before the loop.',
                    $className,
                    $methodName,
                )
            )
                ->identifier('core.noRepositoryCallInLoop')
                ->build();
        }

        return $errors;
    }

    private function isWriteMethod(string $name): bool
    {
        return array_any(
            self::WRITE_PREFIXES,
            fn ($prefix): bool => str_starts_with($name, (string) $prefix),
        );
    }

    /**
     * @param  Node[]  $stmts
     * @return MethodCall[]
     */
    private function findRepositoryMethodCalls(array $stmts): array
    {
        /** @var MethodCall[] $calls */
        $calls = new NodeFinder()->findInstanceOf($stmts, MethodCall::class);

        return array_filter(
            $calls,
            static fn (MethodCall $c): bool => $c->var instanceof PropertyFetch
                && $c->var->var instanceof Variable
                && $c->var->var->name === 'this',
        );
    }

    private function resolveRepositoryClass(MethodCall $call, Scope $scope): ?string
    {
        $type = $scope->getType($call->var);

        foreach ($type->getObjectClassNames() as $className) {
            if (
                str_ends_with($className, 'RepositoryInterface')
                || (str_ends_with($className, 'Repository') && str_starts_with($className, 'App\\'))
            ) {
                return $className;
            }
        }

        return null;
    }
}
