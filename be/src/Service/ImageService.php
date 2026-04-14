<?php

declare(strict_types=1);

namespace App\Service;

use GdImage;
use Maestroerror\HeicToJpg;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

class ImageService
{
    public const string IMAGES_DIRECTORY = 'uploads/images';

    public function __construct(
        private readonly string $publicDirectory,
    ) {
    }

    public function uploadImage(UploadedFile $image, ?string $path = null): string
    {
        $path ??= $this->publicDirectory.self::IMAGES_DIRECTORY;

        if (!is_dir($path)) {
            if (!@mkdir($path, 0755, true) && !is_dir($path)) {
                $error = error_get_last();
                throw new RuntimeException(sprintf(
                    'Failed to create directory "%s": %s',
                    $path,
                    $error['message'] ?? 'Unknown error'
                ));
            }
        }

        if (!is_writable($path)) {
            throw new RuntimeException(sprintf('Directory "%s" is not writable', $path));
        }

        $originalFilename = str_replace(' ', '-', $image->getClientOriginalName());
        $md5 = md5_file($image->getPathname());
        $newFilename = $md5.'-'.$originalFilename;
        $image->move($path, $newFilename);

        return str_replace($this->publicDirectory, '', $path).'/'.$newFilename;
    }

    /**
     * moves an image from one path to another with recursive creation of directories if needed.
     */
    public function moveImage(string $path, string $newPath): string
    {
        $directory = dirname($newPath);
        if (!is_dir($directory)) {
            if (!@mkdir($directory, 0755, true) && !is_dir($directory)) {
                $error = error_get_last();
                throw new RuntimeException(sprintf(
                    'Failed to create directory "%s": %s',
                    $directory,
                    $error['message'] ?? 'Unknown error'
                ));
            }
        }

        if (!is_writable($directory)) {
            throw new RuntimeException(sprintf('Directory "%s" is not writable', $directory));
        }

        rename($path, $newPath);

        return $newPath;
    }

    /**
     * Convert any supported image format to JPEG.
     *
     * @param string $inputPath  path to the input image
     * @param string $outputPath path to save the converted JPEG
     * @param int    $quality    JPEG quality (0–100)
     *
     * @return string true on success, false on failure
     */
    public function convertToJpeg(string $inputPath, string $outputPath, int $quality = 85): string
    {
        // Get image type
        $imageMime = mime_content_type($inputPath);

        if (!$imageMime) {
            throw new RuntimeException('Unable to determine MIME type of the input image. '.$inputPath);
        }

        // Read EXIF orientation before conversion (works for JPEG and some HEIC)
        $orientation = $this->readExifOrientation($inputPath);

        // Load the image based on MIME type
        switch ($imageMime) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($inputPath);
                break;
            case 'image/png':
                $image = imagecreatefrompng($inputPath);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($inputPath);
                break;
            case 'image/webp':
                $image = imagecreatefromwebp($inputPath);
                break;
            case 'image/bmp':
            case 'image/x-ms-bmp':
                $image = imagecreatefrombmp($inputPath);
                break;
            case 'image/heic':
            case 'image/heif':
                $bin = HeicToJpg::convert($inputPath)->get();
                $image = imagecreatefromstring($bin);
                // If we couldn't read EXIF from the original HEIC, try from the converted JPEG binary
                if (1 === $orientation) {
                    $tmpPath = tempnam(sys_get_temp_dir(), 'heic_exif_');
                    file_put_contents($tmpPath, $bin);
                    $orientation = $this->readExifOrientation($tmpPath);
                    unlink($tmpPath);
                }
                break;
            default:
                throw new RuntimeException('Unsupported image format: '.$imageMime.'. Supported formats are JPEG, PNG, GIF, WebP, BMP, HEIC/HEIF.');
        }

        if (!$image) {
            throw new RuntimeException('Failed to create image from input file. '.$inputPath);
        }

        // Apply EXIF orientation to correct rotation
        $image = $this->applyExifOrientation($image, $orientation);

        $originalName = pathinfo($inputPath, PATHINFO_FILENAME);
        if (!is_dir($outputPath)) {
            if (!@mkdir($outputPath, 0755, true) && !is_dir($outputPath)) {
                $error = error_get_last();
                throw new RuntimeException(sprintf(
                    'Failed to create directory "%s": %s',
                    $outputPath,
                    $error['message'] ?? 'Unknown error'
                ));
            }
        }

        if (!is_writable($outputPath)) {
            throw new RuntimeException(sprintf('Directory "%s" is not writable', $outputPath));
        }

        $newLink = $outputPath.'/'.$originalName.'.jpeg';
        // Convert and save to JPEG
        $success = imagejpeg($image, $newLink, $quality);

        if ($success) {
            unlink($inputPath);
        }

        // Free memory
        imagedestroy($image);

        return $newLink;
    }

    /**
     * Resize an image so its longest side is at most $maxDim pixels.
     * Overwrites the file in place. Returns the same path.
     */
    public function resizeForVision(string $absolutePath, int $maxDim = 1024): string
    {
        $image = imagecreatefromjpeg($absolutePath);
        if (!$image) {
            return $absolutePath;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= $maxDim && $height <= $maxDim) {
            imagedestroy($image);

            return $absolutePath;
        }

        $ratio = min($maxDim / $width, $maxDim / $height);
        $newWidth = (int) round($width * $ratio);
        $newHeight = (int) round($height * $ratio);

        // Don't resize if it would make either dimension below marketplace minimum (600px)
        if ($newWidth < 600 || $newHeight < 600) {
            imagedestroy($image);

            return $absolutePath;
        }

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagejpeg($resized, $absolutePath, 85);

        imagedestroy($image);
        imagedestroy($resized);

        return $absolutePath;
    }

    /**
     * Rotate a JPEG image 90 degrees clockwise, overwriting the file in place.
     */
    public function rotateImage(string $absolutePath): void
    {
        $image = imagecreatefromjpeg($absolutePath);
        if (!$image) {
            throw new RuntimeException('Failed to load image: '.$absolutePath);
        }

        // imagerotate rotates counter-clockwise, so -90 = 90° clockwise
        $rotated = imagerotate($image, -90, 0);
        imagedestroy($image);

        if (!$rotated) {
            throw new RuntimeException('Failed to rotate image');
        }

        imagejpeg($rotated, $absolutePath, 85);
        imagedestroy($rotated);
    }

    /**
     * Read EXIF orientation value from an image file.
     * Returns 1 (normal) if EXIF data is unavailable.
     */
    private function readExifOrientation(string $path): int
    {
        if (!function_exists('exif_read_data')) {
            return 1;
        }

        try {
            $exif = @exif_read_data($path, 'IFD0');
        } catch (Throwable) {
            return 1;
        }

        return $exif['Orientation'] ?? 1;
    }

    /**
     * Apply EXIF orientation to a GD image resource, returning the corrected image.
     *
     * @param GdImage $image       GD image resource
     * @param int     $orientation EXIF orientation value (1–8)
     *
     * @return GdImage corrected image
     */
    private function applyExifOrientation(GdImage $image, int $orientation): GdImage
    {
        $result = match ($orientation) {
            2 => $this->flipImage($image, IMG_FLIP_HORIZONTAL),
            3 => imagerotate($image, 180, 0),
            4 => $this->flipImage($image, IMG_FLIP_VERTICAL),
            5 => $this->flipImage(imagerotate($image, -90, 0) ?: $image, IMG_FLIP_HORIZONTAL),
            6 => imagerotate($image, -90, 0),
            7 => $this->flipImage(imagerotate($image, 90, 0) ?: $image, IMG_FLIP_HORIZONTAL),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        if ($result && $result !== $image) {
            imagedestroy($image);

            return $result;
        }

        return $image;
    }

    private function flipImage(GdImage $image, int $mode): GdImage
    {
        imageflip($image, $mode);

        return $image;
    }

    public function deleteImageFile(string $absolutePath): void
    {
        if (file_exists($absolutePath)) {
            unlink($absolutePath);
        }
    }

    public function getFileSize(string $path): int
    {
        if (!file_exists($path)) {
            throw new RuntimeException('File does not exist: '.$path);
        }

        $size = filesize($path);
        if (false === $size) {
            throw new RuntimeException('Could not get file size for: '.$path);
        }

        return $size;
    }
}
