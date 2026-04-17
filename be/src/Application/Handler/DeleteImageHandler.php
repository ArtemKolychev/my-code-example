<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\DeleteImageCommand;
use App\Application\Service\ImageServiceInterface;
use App\Domain\Repository\ImageRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: DeleteImageCommand::class)]
final readonly class DeleteImageHandler
{
    public function __construct(
        private ImageRepositoryInterface $imageRepository,
        private ImageServiceInterface $imageService,
        #[Autowire('%public_directory%')]
        private string $publicDirectory,
    ) {
    }

    public function __invoke(DeleteImageCommand $command): void
    {
        $image = $this->imageRepository->findById($command->imageId);

        if (!$image) {
            return;
        }

        $this->imageService->deleteImageFile($this->publicDirectory.'/'.$image->getLink());
        $this->imageRepository->remove($image);
    }
}
