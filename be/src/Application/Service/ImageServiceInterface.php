<?php

declare(strict_types=1);

namespace App\Application\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface ImageServiceInterface
{
    public const string IMAGES_DIRECTORY = 'uploads/images';

    public function uploadImage(UploadedFile $image, ?string $path = null): string;

    public function moveImage(string $path, string $newPath): string;

    public function convertToJpeg(string $inputPath, string $outputPath, int $quality = 85): string;

    public function resizeForVision(string $absolutePath, int $maxDim = 1024): string;

    public function rotateImage(string $absolutePath): void;

    public function deleteImageFile(string $absolutePath): void;

    public function getFileSize(string $path): int;
}
