<?php

declare(strict_types=1);

namespace Tests\PHPStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/** @implements Rule<Node\Scalar\String_> */
final class NoCrossSchemaJoinRule implements Rule
{
    private const array KNOWN_SCHEMAS = ['auth', 'users', 'tenants'];

    public function getNodeType(): string
    {
        return Node\Scalar\String_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $className = $scope->getClassReflection()?->getName() ?? '';

        if (str_contains($className, 'Infrastructure\\Query\\CrossSchema')) {
            return [];
        }

        $sql = strtolower($node->value);

        if (! str_contains($sql, 'join') && ! str_contains($sql, 'from ')) {
            return [];
        }

        return $this->detectCrossSchemaUsage($sql, $className);
    }

    /** @return list<IdentifierRuleError> */
    private function detectCrossSchemaUsage(string $sql, string $className): array
    {
        foreach (self::KNOWN_SCHEMAS as $schema) {
            if ((bool) preg_match('/\b'.$schema.'\.\w+/', $sql)) {
                return [
                    RuleErrorBuilder::message(sprintf(
                        'Cross-schema query detected in %s. '
                        .'Move to Infrastructure\\Query\\CrossSchema\\ with a justification comment.',
                        $className !== '' ? $className : 'unknown class',
                    ))
                        ->identifier('core.noCrossSchemaJoin')
                        ->build(),
                ];
            }
        }

        return [];
    }
}
