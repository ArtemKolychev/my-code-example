<?php

declare(strict_types=1);

namespace App\Domain\Security;

/**
 * Domain-level permission attribute strings used in #[IsGranted] attributes.
 *
 * The actual vote logic lives in Infrastructure\Security voters;
 * only the string identifiers belong to Domain so that UI Actions can
 * reference them without depending on Infrastructure.
 */
final class Permission
{
    // Article permissions
    public const string ARTICLE_EDIT = 'ARTICLE_EDIT';
    public const string ARTICLE_DELETE = 'ARTICLE_DELETE';
    public const string ARTICLE_WITHDRAW = 'ARTICLE_WITHDRAW';
    public const string ARTICLE_VIEW = 'ARTICLE_VIEW';

    // Image permissions
    public const string IMAGE_DELETE = 'IMAGE_DELETE';
    public const string IMAGE_ROTATE = 'IMAGE_ROTATE';
}
