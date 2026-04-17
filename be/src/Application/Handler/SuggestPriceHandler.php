<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\ClickerCommand;
use App\Application\Command\SuggestPriceCommand;
use App\Application\DTO\Clicker\ActionSuggestPricePayload;
use App\Application\Logging\TraceContext;
use App\Domain\Exception\ArticleNotFoundException;
use App\Domain\Repository\ArticleRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(handles: SuggestPriceCommand::class)]
final readonly class SuggestPriceHandler
{
    public function __construct(
        private ArticleRepositoryInterface $articleRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SuggestPriceCommand $message): void
    {
        $article = $this->articleRepository->findById($message->getArticleId());
        if (!$article) {
            throw ArticleNotFoundException::forId($message->getArticleId());
        }

        $jobId = uniqid('price_', true);

        $this->messageBus->dispatch(new ClickerCommand('action.suggest_price', new ActionSuggestPricePayload(
            jobId: $jobId,
            articleId: $article->getId(),
            title: $article->getTitle() ?? '',
            description: $article->getDescription() ?? '',
            condition: $article->condition?->label() ?? '',
        )));

        TraceContext::setJobId($jobId);
        $this->logger->info('SuggestPrice command sent to ai-agent via AMQP', [
            'jobId' => $jobId,
            'articleId' => $article->getId(),
        ]);
    }
}
