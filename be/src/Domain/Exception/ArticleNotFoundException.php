<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

final class ArticleNotFoundException extends RuntimeException
{
    public static function forId(int $id): self
    {
        return new self("Article id:[{$id}] not found");
    }
}
