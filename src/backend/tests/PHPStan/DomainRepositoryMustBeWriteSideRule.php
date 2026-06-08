<?php

declare(strict_types=1);

namespace Tests\PHPStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/** @implements Rule<Node\Stmt\Interface_> */
final class DomainRepositoryMustBeWriteSideRule implements Rule
{
    public function getNodeType(): string
    {
        return Node\Stmt\Interface_::class;
    }

    /** @return list<IdentifierRuleError> */
    public function processNode(Node $node, Scope $scope): array
    {
        $className = $scope->getClassReflection()?->getName() ?? '';

        if (! (bool) preg_match('/\\\\Domain\\\\.*\\\\Repository\\\\/', $className)) {
            return [];
        }

        $errors = [];

        foreach ($node->getMethods() as $method) {
            $name = $method->name->toString();

            if (! $this->isWriteSideMethod($name)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Domain repository %s declares disallowed method %s(). '
                    .'Domain repositories must only declare save(), delete(), '
                    .'and findById-style methods (e.g. findById, getById, findByUserId). '
                    .'Move query methods to Application\\Contract\\*QueryInterface.',
                    $className,
                    $name,
                ))
                    ->identifier('core.domainRepositoryMustBeWriteSide')
                    ->build();
            }
        }

        return $errors;
    }

    private function isWriteSideMethod(string $name): bool
    {
        return in_array($name, ['save', 'delete'], true)
            || (bool) preg_match('/^(find|get)By\w*Id$/', $name);
    }
}
