<?php

declare(strict_types=1);

namespace App\Tests\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\ErrorSuppress;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Forbids the @ error-suppression operator anywhere in App\ code.
 *
 * @ hides real errors instead of handling them, makes debugging impossible,
 * and signals that the code relies on undefined behaviour.
 * Use explicit guards (file_exists, is_resource, …) or try/catch instead.
 *
 * ❌  @unlink($path);
 * ✅  if (file_exists($path)) { unlink($path); }
 *
 * @implements Rule<ErrorSuppress>
 */
final class NoErrorSuppressionRule implements Rule
{
    public function getNodeType(): string
    {
        return ErrorSuppress::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $className = $scope->getClassReflection()?->getName() ?? '';

        if (!str_starts_with($className, 'App\\')) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'The @ error-suppression operator is forbidden. '
                .'Use an explicit guard (file_exists, is_resource, try/catch) instead.'
            )
                ->identifier('bazar.noErrorSuppression')
                ->build(),
        ];
    }
}
