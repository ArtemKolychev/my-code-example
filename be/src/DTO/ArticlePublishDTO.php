<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Article;
use App\Entity\User;

class ArticlePublishDTO
{
    public ?string $title;
    public ?string $description;
    public ?float $price;
    /** @var string[]|null */
    public ?array $images;
    public ?string $phone;
    public ?string $address;
    public ?int $id;
    public ?string $serviceLogin = null;
    public ?string $servicePassword = null;

    public function __construct(User $user, Article $article)
    {
        $this->id = $article->getId();
        $this->title = $article->getTitle();
        $this->description = $article->getDescription();
        $this->price = $article->getPrice();
        $this->images = array_values(array_filter(
            array_map(fn ($image) => $image->getLink(), $article->getImages()->toArray()),
            fn ($link): bool => is_string($link),
        ));
        $this->phone = $user->getPhone();
        $this->address = $user->getAddress();
    }
}
