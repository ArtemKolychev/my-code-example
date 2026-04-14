<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class EditArticleRequest
{
    /**
     * @param UploadedFile[] $images
     */
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly float|int|null $price = null,
        public readonly array $images = [],
    ) {
    }
}
