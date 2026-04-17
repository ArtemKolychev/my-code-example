<?php

declare(strict_types=1);

namespace App\Infrastructure\Service;

use App\Application\Service\ImageServiceInterface;
use GdImage;
use Maestroerror\HeicToJpg;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

class ImageService implements ImageServiceInterface
{
    public const string IMAGES_DIRECTORY = 'uploads/images';

    public function __construct(
        private readonly string $publicDirectory,
    ) {
    }

    public function uploadImage(UploadedFile $image, ?string $path = null): string
    {
        $path ??= $this->publicDirectory.self::IMAGES_DIRECTORY;
        $this->ensureWritableDirectory($path);

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
        $this->ensureWritableDirectory(dirname($newPath));

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
        $image = $this->loadImageFromMime($inputPath, $imageMime, $orientation);

        // Apply EXIF orientation to correct rotation
        $image = $this->applyExifOrientation($image, $orientation);

        $this->ensureWritableDirectory($outputPath);

        $originalName = pathinfo($inputPath, PATHINFO_FILENAME);
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

        if (max($width, $height) <= $maxDim) {
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

    /**
     * Load a GdImage from the given file path based on its MIME type.
     * For HEIC/HEIF, also updates $orientation by reading EXIF from the converted binary.
     *
     * @param int $orientation passed by reference; updated for HEIC when original EXIF is unavailable
     */
    private function loadImageFromMime(string $inputPath, string $imageMime, int &$orientation): GdImage
    {
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
                if (false === $image) {
                    throw new RuntimeException('Failed to create image from HEIC input. '.$inputPath);
                }
                // If we couldn't read EXIF from the original HEIC, try from the converted JPEG binary
                if (1 === $orientation) {
                    $tmpPath = tempnam(sys_get_temp_dir(), 'heic_exif_');
                    file_put_contents($tmpPath, $bin);
                    $orientation = $this->readExifOrientation($tmpPath);
                    unlink($tmpPath);
                }

                return $image;
            default:
                throw new RuntimeException('Unsupported image format: '.$imageMime.'. Supported formats are JPEG, PNG, GIF, WebP, BMP, HEIC/HEIF.');
        }

        if (!$image) {
            throw new RuntimeException('Failed to create image from input file. '.$inputPath);
        }

        return $image;
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
            $exif = exif_read_data($path, 'IFD0');
        } catch (Throwable) {
            return 1;
        }

        if (!is_array($exif)) {
            return 1;
        }

        $orientation = $exif['Orientation'] ?? 1;

        return is_int($orientation) ? $orientation : 1;
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
            5 => $this->flipImage($this->rotateOrOriginal($image, -90), IMG_FLIP_HORIZONTAL),
            6 => imagerotate($image, -90, 0),
            7 => $this->flipImage($this->rotateOrOriginal($image, 90), IMG_FLIP_HORIZONTAL),
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

    /**
     * Ensures the given directory exists and is writable, creating it if needed.
     */
    private function ensureWritableDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        if (!is_dir($path)) {
            $error = error_get_last();
            throw new RuntimeException(sprintf('Failed to create directory "%s": %s', $path, $error['message'] ?? 'Unknown error'));
        }

        if (!is_writable($path)) {
            throw new RuntimeException(sprintf('Directory "%s" is not writable', $path));
        }
    }

    /**
     * Rotates a GdImage by the given degrees, returning the original if rotation fails.
     */
    private function rotateOrOriginal(GdImage $image, int $degrees): GdImage
    {
        return imagerotate($image, $degrees, 0) ?: $image;
    }
}
