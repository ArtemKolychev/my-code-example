<?php

declare(strict_types=1);

namespace App\Application\DTO\Payload;

use App\Domain\Entity\Article;
use App\Domain\Enum\Category;
use App\Domain\Enum\Condition;

/**
 * Form DTO for article edit. Mutable so Symfony's PropertyAccessor can populate it.
 */
class ArticleUpdatePayload
{
    /** Hidden field — value comes back as string from HTTP, cast to int where needed. */
    public int|string|null $id = null;

    public ?string $title = null;

    public ?string $description = null;

    public ?float $price = null;

    public ?Category $category = null;

    public ?Condition $condition = null;

    /** @var array<string, mixed>|null JSON-encoded meta fields submitted via hidden input. */
    public ?array $metaFields = null;

    public static function fromArticle(Article $article): self
    {
        $payload = new self();
        $payload->id = $article->getId();
        $payload->title = $article->getTitle();
        $payload->description = $article->getDescription();
        $payload->price = $article->getPrice();
        $payload->category = $article->getCategory();
        $payload->condition = $article->getCondition();

        return $payload;
    }
}
