<?php

declare(strict_types=1);

namespace App\Tests\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Forbids non-empty string literal defaults on properties in Domain and Application layers.
 *
 * String literals as property defaults (e.g., `$status = 'pending'`) indicate
 * missing enums. Use a typed enum value as the default instead:
 *
 *   ❌  private string $status = 'pending';
 *   ✅  private ArticleStatus $status = ArticleStatus::Pending;
 *
 * Empty strings ('') are allowed as zero-values for optional string fields.
 *
 * @implements Rule<Property>
 */
final class NoStringLiteralPropertyDefaultRule implements Rule
{
    private const array PROTECTED_NAMESPACES = [
        'App\\Domain\\',
        'App\\Application\\Command\\',
        'App\\Application\\Handler\\',
    ];

    public function getNodeType(): string
    {
        return Property::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $className = $scope->getClassReflection()?->getName() ?? '';

        if (!$this->isInProtectedNamespace($className)) {
            return [];
        }

        $errors = [];

        foreach ($node->props as $prop) {
            if ($prop->default instanceof String_ && '' !== $prop->default->value) {
                $errors[] = RuleErrorBuilder::message(
                    sprintf(
                        'Property $%s in %s must not have a string literal default "%s". '
                        .'Use an Enum case (e.g., SomeStatus::Pending) instead.',
                        (string) $prop->name,
                        $className,
                        $prop->default->value,
                    )
                )
                    ->identifier('bazar.noStringLiteralPropertyDefault')
                    ->build();
            }
        }

        return $errors;
    }

    private function isInProtectedNamespace(string $className): bool
    {
        return array_any(self::PROTECTED_NAMESPACES, fn ($ns): bool => str_starts_with($className, (string) $ns));
    }
}
