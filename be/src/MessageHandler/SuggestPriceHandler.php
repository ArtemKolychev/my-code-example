<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Logging\TraceContext;
use App\Message\ClickerCommand;
use App\Message\SuggestPriceMessage;
use App\Repository\ArticleRepository;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
readonly class SuggestPriceHandler
{
    public function __construct(
        private ArticleRepository $articleRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SuggestPriceMessage $message): void
    {
        $article = $this->articleRepository->find($message->getArticleId());
        if (!$article) {
            throw new RuntimeException("Article id:[{$message->getArticleId()}] not found");
        }

        $jobId = uniqid('price_', true);

        $this->messageBus->dispatch(new ClickerCommand('action.suggest_price', [
            'jobId' => $jobId,
            'articleId' => $article->getId(),
            'title' => $article->getTitle() ?? '',
            'description' => $article->getDescription() ?? '',
            'condition' => $article->condition?->label() ?? '',
        ]));

        TraceContext::setJobId($jobId);
        $this->logger->info('SuggestPrice command sent to ai-agent via AMQP', [
            'jobId' => $jobId,
            'articleId' => $article->getId(),
        ]);
    }
}
