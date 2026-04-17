<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\ClickerCommand;
use App\Application\Command\EnrichVehicleCommand;
use App\Application\DTO\Clicker\ActionEnrichVehiclePayload;
use App\Application\DTO\Clicker\ImageData;
use App\Application\Logging\TraceContext;
use App\Domain\Entity\Image;
use App\Domain\Exception\ArticleNotFoundException;
use App\Domain\Repository\ArticleRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(handles: EnrichVehicleCommand::class)]
final readonly class EnrichVehicleHandler
{
    public function __construct(
        private ArticleRepositoryInterface $articleRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
        private string $internalAppUrl,
    ) {
    }

    public function __invoke(EnrichVehicleCommand $message): void
    {
        $article = $this->articleRepository->findById($message->getArticleId());
        if (!$article) {
            throw ArticleNotFoundException::forId($message->getArticleId());
        }

        $jobId = uniqid('enrich_vehicle_', true);

        $articleImages = array_values(array_map(
            fn (Image $img): ImageData => new ImageData(
                path: (string) $img->getLink(),
                url: rtrim($this->internalAppUrl, '/').'/'.ltrim((string) $img->getLink(), '/'),
            ),
            $article->getImages()->toArray(),
        ));

        $this->messageBus->dispatch(new ClickerCommand('action.enrich_vehicle', new ActionEnrichVehiclePayload(
            jobId: $jobId,
            articleId: $article->getId(),
            vin: $message->getVin() ?? (is_string($article->getMeta()['vin'] ?? null) ? $article->getMeta()['vin'] : null),
            spz: $message->getSpz() ?? (is_string($article->getMeta()['spz'] ?? null) ? $article->getMeta()['spz'] : null),
            articleImages: $articleImages,
        )));

        TraceContext::setJobId($jobId);
        $this->logger->info('EnrichVehicle command sent to ai-agent via AMQP', [
            'jobId' => $jobId,
            'articleId' => $article->getId(),
            'vin' => $message->getVin(),
            'spz' => $message->getSpz(),
        ]);
    }
}
