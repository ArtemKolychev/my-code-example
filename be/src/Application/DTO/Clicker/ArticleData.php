<?php

declare(strict_types=1);

namespace App\Application\DTO\Clicker;

final readonly class ArticleData
{
    /**
     * @param list<PublishImageData> $images
     * @param array<string, mixed>   $meta
     */
    public function __construct(
        public ?int $id,
        public ?string $title,
        public ?string $description,
        public ?int $price,
        public ?string $name,
        public ?string $email,
        public ?string $phone,
        public ?string $address,
        public ?string $zip,
        public array $images,
        public ?string $condition,
        public array $meta,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'price' => $this->price,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'zip' => $this->zip,
            'images' => array_map(static fn (PublishImageData $img): array => $img->toArray(), $this->images),
            'condition' => $this->condition,
            'meta' => $this->meta,
        ];
    }
}
