<?php

declare(strict_types=1);

namespace App\Application\Query;

use App\Application\DTO\Response\ArticleResponse;
use App\Domain\Entity\User;

interface ArticleProviderInterface
{
    public function findForUser(int $id, User $user): ?ArticleResponse;

    /** @return ArticleResponse[] */
    public function listForUser(User $user): array;
}
