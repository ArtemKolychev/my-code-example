<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Entity\Article;
use App\Domain\Entity\User;
use App\Domain\Security\Permission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Centralized ownership ACL for Article entities.
 *
 * Attributes:
 *   ARTICLE_EDIT     — owner can edit title/description/price/images
 *   ARTICLE_DELETE   — owner can delete
 *   ARTICLE_WITHDRAW — owner can withdraw from platforms
 *   ARTICLE_VIEW     — owner can view full detail (includes private data)
 *
 * @extends Voter<string, Article>
 */
final class ArticleVoter extends Voter
{
    public const string EDIT = Permission::ARTICLE_EDIT;
    public const string DELETE = Permission::ARTICLE_DELETE;
    public const string WITHDRAW = Permission::ARTICLE_WITHDRAW;
    public const string VIEW = Permission::ARTICLE_VIEW;

    private const array ATTRIBUTES = [
        Permission::ARTICLE_EDIT,
        Permission::ARTICLE_DELETE,
        Permission::ARTICLE_WITHDRAW,
        Permission::ARTICLE_VIEW,
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::ATTRIBUTES, true)
            && $subject instanceof Article;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Article $article */
        $article = $subject;

        return $article->user === $user;
    }
}
