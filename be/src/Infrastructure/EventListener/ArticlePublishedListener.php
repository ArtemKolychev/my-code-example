<?php

declare(strict_types=1);

namespace App\Infrastructure\EventListener;

use App\Domain\Event\ArticlePublishedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: ArticlePublishedEvent::class)]
class ArticlePublishedListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ArticlePublishedEvent $event): void
    {
        $article = $event->getArticle();
        $response = $event->getClickerResponse();

        $this->logger->info('Article published', [
            'articleId' => $article->getId(),
            'isOk' => $response['isOk'] ?? false,
            'resource' => $response['resource'] ?? null,
        ]);
    }
}
