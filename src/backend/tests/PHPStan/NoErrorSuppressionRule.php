<?php

declare(strict_types=1);

namespace Tests\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\ErrorSuppress;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/** @implements Rule<ErrorSuppress> */
final class NoErrorSuppressionRule implements Rule
{
    public function getNodeType(): string
    {
        return ErrorSuppress::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $className = $scope->getClassReflection()?->getName() ?? '';

        if (! str_starts_with($className, 'App\\')) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'The @ error-suppression operator is forbidden. '
                .'Use an explicit guard (file_exists, is_resource, try/catch) instead.'
            )
                ->identifier('core.noErrorSuppression')
                ->build(),
        ];
    }
}
