<?php

declare(strict_types=1);

namespace App\Tests\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Symfony\Component\Form\AbstractType;

/**
 * Enforces that all Symfony Form types in EntryPoint\Form are final.
 *
 * Form types are leaf-node infrastructure classes — they should never be extended.
 * Making them final prevents accidental inheritance and signals clear intent.
 *
 * ❌  class EditArticleType extends AbstractType { ... }
 * ✅  final class EditArticleType extends AbstractType { ... }
 *
 * @implements Rule<Class_>
 */
final class FormTypesMustBeFinalRule implements Rule
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $className = $scope->getClassReflection()?->getName() ?? '';

        if (!str_starts_with($className, 'App\\EntryPoint\\Form\\')) {
            return [];
        }

        // Only check classes that actually extend AbstractType
        $classReflection = $scope->getClassReflection();
        if (null === $classReflection || !$classReflection->isSubclassOf(AbstractType::class)) {
            return [];
        }

        if ($node->isFinal()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                sprintf(
                    'Form type %s must be declared as "final class". '
                    .'Form types are leaf-node infrastructure classes that should never be extended.',
                    $className,
                )
            )
                ->identifier('bazar.formTypeMustBeFinal')
                ->build(),
        ];
    }
}
