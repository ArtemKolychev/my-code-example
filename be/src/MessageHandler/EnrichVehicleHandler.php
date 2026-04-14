<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Logging\TraceContext;
use App\Message\ClickerCommand;
use App\Message\EnrichVehicleMessage;
use App\Repository\ArticleRepository;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
readonly class EnrichVehicleHandler
{
    public function __construct(
        private ArticleRepository $articleRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
        private string $internalAppUrl,
    ) {
    }

    public function __invoke(EnrichVehicleMessage $message): void
    {
        $article = $this->articleRepository->find($message->getArticleId());
        if (!$article) {
            throw new RuntimeException("Article id:[{$message->getArticleId()}] not found");
        }

        $jobId = uniqid('enrich_vehicle_', true);

        $articleImages = array_values(array_map(
            fn ($img) => [
                'path' => $img->getLink(),
                'url' => rtrim($this->internalAppUrl, '/').'/'.ltrim((string) $img->getLink(), '/'),
            ],
            $article->getImages()->toArray(),
        ));

        $this->messageBus->dispatch(new ClickerCommand('action.enrich_vehicle', [
            'jobId' => $jobId,
            'articleId' => $article->getId(),
            'vin' => $message->getVin() ?? ($article->getMeta()['vin'] ?? null),
            'spz' => $message->getSpz() ?? ($article->getMeta()['spz'] ?? null),
            'articleImages' => $articleImages,
        ]));

        TraceContext::setJobId($jobId);
        $this->logger->info('EnrichVehicle command sent to ai-agent via AMQP', [
            'jobId' => $jobId,
            'articleId' => $article->getId(),
            'vin' => $message->getVin(),
            'spz' => $message->getSpz(),
        ]);
    }
}
