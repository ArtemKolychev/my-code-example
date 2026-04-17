<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Command\GroupImagesCommand;
use App\Domain\Entity\ImageBatch;
use App\Domain\Entity\User;
use App\Domain\Exception\InsufficientTokensException;
use App\Domain\Repository\ImageBatchRepositoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Orchestrates image uploads: stores files, converts to JPEG, dispatches async AI grouping.
 */
class ImageUploadService
{
    public function __construct(
        private readonly ImageBatchRepositoryInterface $imageBatchRepository,
        private readonly ImageServiceInterface $imageService,
        private readonly MessageBusInterface $messageBus,
        private readonly string $publicDirectory,
    ) {
    }

    /**
     * Upload images, create an ImageBatch, and dispatch for async AI processing.
     *
     * @param UploadedFile[] $images
     *
     * @return ImageBatch the batch entity (with id and jobId set)
     *
     * @throws InsufficientTokensException if the user has no AI tokens remaining
     */
    public function uploadImages(array $images, User $user): ImageBatch
    {
        if (!$user->hasTokens()) {
            throw InsufficientTokensException::forUser((int) $user->getId());
        }

        $imagePaths = array_map($this->imageService->uploadImage(...), $images);

        foreach ($imagePaths as $i => $imagePath) {
            $quality = (int) ceil(100 / ($this->imageService->getFileSize($this->publicDirectory.$imagePath) / 1024 / 1024));

            $dirname = pathinfo($imagePath, PATHINFO_DIRNAME);
            $newPath = $this->imageService->convertToJpeg(
                $this->publicDirectory.$imagePath,
                $this->publicDirectory.$dirname,
                min($quality, 100),
            );
            $imagePaths[$i] = str_replace($this->publicDirectory, '', $newPath);

            $this->imageService->resizeForVision($newPath);
        }

        $batch = new ImageBatch();
        $batch->setUser($user);
        $batch->setImagePaths($imagePaths);
        $batch->startProcessing(uniqid('group_', true));

        $this->imageBatchRepository->save($batch);

        $this->messageBus->dispatch(new GroupImagesCommand((int) $batch->getId()));

        return $batch;
    }
}
