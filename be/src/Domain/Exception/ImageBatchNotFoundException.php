<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

final class ImageBatchNotFoundException extends RuntimeException
{
    public static function forId(int $id): self
    {
        return new self("ImageBatch id:[{$id}] not found");
    }
}
