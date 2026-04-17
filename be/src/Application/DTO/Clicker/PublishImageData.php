<?php

declare(strict_types=1);

namespace App\Application\DTO\Clicker;

/** Image with name + mimetype + URL, used by the clicker publish action. */
final readonly class PublishImageData
{
    public function __construct(
        public string $name,
        public string $mimetype,
        public string $url,
    ) {
    }

    /** @return array{name: string, mimetype: string, url: string} */
    public function toArray(): array
    {
        return ['name' => $this->name, 'mimetype' => $this->mimetype, 'url' => $this->url];
    }
}
