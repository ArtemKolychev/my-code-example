<?php

declare(strict_types=1);

namespace Tests\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/** @implements Rule<Class_> */
final class ActionsMustUseTypedRequestRule implements Rule
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            return [];
        }

        $className = $classReflection->getName();
        if (! str_contains($className, '\\Http\\Action\\')) {
            return [];
        }

        foreach ($node->getMethods() as $method) {
            if ($method->name->name !== '__invoke') {
                continue;
            }

            $violation = $this->findRequestViolation($method, $className);
            if ($violation !== null) {
                return [$violation];
            }
        }

        return [];
    }

    private function findRequestViolation(ClassMethod $method, string $className): ?IdentifierRuleError
    {
        foreach ($method->params as $param) {
            if (! ($param->type instanceof FullyQualified)) {
                continue;
            }

            if ($param->type->toString() !== 'Illuminate\\Http\\Request') {
                continue;
            }

            return RuleErrorBuilder::message(sprintf(
                'Action %s::__invoke() must type-hint a FormRequest subclass with getBody(): VO, not Illuminate\\Http\\Request directly.',
                $className,
            ))
                ->identifier('core.actionMustUseTypedRequest')
                ->build();
        }

        return null;
    }
}
