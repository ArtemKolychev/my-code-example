<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\Entity\Article;
use Symfony\Contracts\EventDispatcher\Event;

class ArticlePublishedEvent extends Event
{
    /**
     * @param array<string, mixed> $clickerResponse
     */
    public function __construct(
        private readonly Article $article,
        private readonly array $clickerResponse,
    ) {
    }

    public function getArticle(): Article
    {
        return $this->article;
    }

    /** @return array<string, mixed> */
    public function getClickerResponse(): array
    {
        return $this->clickerResponse;
    }
}
