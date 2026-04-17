<?php

declare(strict_types=1);

namespace App\Application\DTO\Clicker;

/** Image with path + URL, used by AI-agent actions (group_images, enrich_vehicle). */
final readonly class ImageData
{
    public function __construct(
        public string $path,
        public string $url,
    ) {
    }

    /** @return array{path: string, url: string} */
    public function toArray(): array
    {
        return ['path' => $this->path, 'url' => $this->url];
    }
}
