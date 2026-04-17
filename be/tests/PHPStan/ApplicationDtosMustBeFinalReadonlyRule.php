<?php

declare(strict_types=1);

namespace App\Tests\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Enforces that Application Request and Response DTOs are final readonly classes.
 *
 * Request DTOs (Application\DTO\Request\*) are immutable input wrappers.
 * Response DTOs (Application\DTO\Response\*) are immutable read models / view models.
 * Both must be final readonly to prevent mutation.
 *
 * Payload DTOs (Application\DTO\Payload\*) are intentionally EXCLUDED — they must
 * remain mutable because Symfony's PropertyAccessor writes to them during form binding.
 *
 * ❌  class ArticleInputRequest { ... }
 * ✅  final readonly class ArticleInputRequest { ... }
 *
 * @implements Rule<Class_>
 */
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

        if (!$this->isInProtectedNamespace($className)) {
            return [];
        }

        if ($node->isFinal() && $node->isReadonly()) {
            return [];
        }

        $missing = [];
        if (!$node->isFinal()) {
            $missing[] = 'final';
        }
        if (!$node->isReadonly()) {
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
                ->identifier('bazar.applicationDtoMustBeFinalReadonly')
                ->build(),
        ];
    }

    private function isInProtectedNamespace(string $className): bool
    {
        return array_any(self::PROTECTED_NAMESPACES, fn ($ns): bool => str_starts_with($className, (string) $ns));
    }
}
