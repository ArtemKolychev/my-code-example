<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\ClickerCommand;
use App\Application\Command\GroupImagesCommand;
use App\Application\DTO\Clicker\ActionGroupImagesPayload;
use App\Application\DTO\Clicker\ImageData;
use App\Application\Logging\TraceContext;
use App\Domain\Exception\ImageBatchNotFoundException;
use App\Domain\Repository\ImageBatchRepositoryInterface;
use App\Domain\ValueObject\BatchStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(handles: GroupImagesCommand::class)]
final readonly class GroupImagesHandler
{
    public function __construct(
        private ImageBatchRepositoryInterface $imageBatchRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
        private string $internalAppUrl,
    ) {
    }

    public function __invoke(GroupImagesCommand $message): void
    {
        $batch = $this->imageBatchRepository->findById($message->getBatchId());
        if (!$batch) {
            throw ImageBatchNotFoundException::forId($message->getBatchId());
        }

        $batch->setStatus(BatchStatus::Processing);

        $articleImages = array_map(
            fn (string $path): ImageData => new ImageData(
                path: $path,
                url: rtrim($this->internalAppUrl, '/').'/'.ltrim($path, '/'),
            ),
            $batch->getImagePaths(),
        );

        $jobId = $batch->getJobId();

        $this->messageBus->dispatch(new ClickerCommand('action.group_images', new ActionGroupImagesPayload(
            jobId: $jobId,
            batchId: $batch->getId(),
            articleImages: array_values($articleImages),
            vehicleIdentifier: $message->getVehicleIdentifier(),
            condition: $batch->condition,
        )));

        TraceContext::setJobId($jobId);
        $this->logger->info('GroupImages command sent to ai-agent via AMQP', [
            'jobId' => $jobId,
            'batchId' => $batch->getId(),
            'imageCount' => count($articleImages),
        ]);
    }
}
