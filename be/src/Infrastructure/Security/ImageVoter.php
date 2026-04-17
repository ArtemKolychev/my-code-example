<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Entity\Image;
use App\Domain\Entity\User;
use App\Domain\Security\Permission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Centralized ownership ACL for Image entities.
 *
 * Ownership is determined transitively: the image belongs to the user
 * who owns the parent Article.
 *
 * Attributes:
 *   IMAGE_DELETE — owner can delete the image file and record
 *   IMAGE_ROTATE — owner can rotate the image
 *
 * @extends Voter<string, Image>
 */
final class ImageVoter extends Voter
{
    public const string DELETE = Permission::IMAGE_DELETE;
    public const string ROTATE = Permission::IMAGE_ROTATE;

    private const array ATTRIBUTES = [
        Permission::IMAGE_DELETE,
        Permission::IMAGE_ROTATE,
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::ATTRIBUTES, true)
            && $subject instanceof Image;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Image $image */
        $image = $subject;

        return $image->article?->user === $user;
    }
}
