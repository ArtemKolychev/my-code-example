<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Logging\TraceContext;
use App\Message\ClickerCommand;
use App\Message\GroupImagesMessage;
use App\Repository\ImageBatchRepository;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
readonly class GroupImagesHandler
{
    public function __construct(
        private ImageBatchRepository $batchRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
        private string $internalAppUrl,
    ) {
    }

    public function __invoke(GroupImagesMessage $message): void
    {
        $batch = $this->batchRepository->find($message->getBatchId());
        if (!$batch) {
            throw new RuntimeException("ImageBatch id:[{$message->getBatchId()}] not found");
        }

        $batch->setStatus('processing');

        $articleImages = array_map(
            fn (string $path) => [
                'path' => $path,
                'url' => rtrim($this->internalAppUrl, '/').'/'.ltrim($path, '/'),
            ],
            $batch->getImagePaths(),
        );

        $jobId = $batch->getJobId();

        $this->messageBus->dispatch(new ClickerCommand('action.group_images', [
            'jobId' => $jobId,
            'batchId' => $batch->getId(),
            'articleImages' => $articleImages,
            'vehicleIdentifier' => $message->getVehicleIdentifier(),
            'condition' => $batch->condition,
        ]));

        TraceContext::setJobId($jobId);
        $this->logger->info('GroupImages command sent to ai-agent via AMQP', [
            'jobId' => $jobId,
            'batchId' => $batch->getId(),
            'imageCount' => count($articleImages),
        ]);
    }
}
