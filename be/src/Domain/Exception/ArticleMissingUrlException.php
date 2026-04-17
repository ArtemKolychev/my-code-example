<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

final class ArticleMissingUrlException extends RuntimeException
{
    public static function forId(int $id): self
    {
        return new self("Article id:[{$id}] has no articleUrl — cannot delete");
    }
}
