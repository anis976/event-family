<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class MessagePhotoProcessor
{
    private const array ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        #[Autowire('%ef.message_photos.max_dimension%')]
        private readonly int $maxDimension,
        #[Autowire('%ef.message_photos.webp_quality%')]
        private readonly int $webpQuality,
    ) {
    }

    public function resolveExtensionForMime(string $mimeType): ?string
    {
        return self::ALLOWED_MIME_TYPES[$mimeType] ?? null;
    }

    /**
     * @return list<string>
     */
    public function allowedMimeTypes(): array
    {
        return array_keys(self::ALLOWED_MIME_TYPES);
    }

    /**
     * @param array{x: int, y: int, width: int, height: int}|null $crop
     */
    public function processAndSave(
        string $sourcePath,
        string $destinationPath,
        string $mimeType,
        ?array $crop = null,
    ): void {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('flash.message.photo_gd_required');
        }

        $image = $this->createImageFromPath($sourcePath, $mimeType);
        if (false === $image) {
            throw new \InvalidArgumentException('flash.message.photo_read_failed');
        }

        $crop = $this->normalizeCrop($crop, imagesx($image), imagesy($image));
        if (null !== $crop) {
            $cropped = imagecrop($image, $crop);
            imagedestroy($image);
            if (false === $cropped) {
                throw new \InvalidArgumentException('flash.message.photo_crop_failed');
            }
            $image = $cropped;
        }

        $image = $this->resizeToMaxDimension($image);
        $this->saveOptimizedImage($image, $destinationPath);
        imagedestroy($image);
    }

    /**
     * @param array{x: int, y: int, width: int, height: int}|null $crop
     *
     * @return array{x: int, y: int, width: int, height: int}|null
     */
    private function normalizeCrop(?array $crop, int $maxWidth, int $maxHeight): ?array
    {
        if (null === $crop) {
            return null;
        }

        $width = (int) ($crop['width'] ?? 0);
        $height = (int) ($crop['height'] ?? 0);
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        $x = max(0, min((int) ($crop['x'] ?? 0), $maxWidth - 1));
        $y = max(0, min((int) ($crop['y'] ?? 0), $maxHeight - 1));
        $width = max(1, min($width, $maxWidth - $x));
        $height = max(1, min($height, $maxHeight - $y));

        return [
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
        ];
    }

    private function resizeToMaxDimension(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $maxDimension = max(1, $this->maxDimension);
        $longestSide = max($width, $height);

        if ($longestSide <= $maxDimension) {
            return $image;
        }

        $scale = $maxDimension / $longestSide;
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $resized = imagescale($image, $newWidth, $newHeight);
        imagedestroy($image);

        if (false === $resized) {
            throw new \InvalidArgumentException('flash.message.photo_resize_failed');
        }

        return $resized;
    }

    /**
     * @return \GdImage|false
     */
    private function createImageFromPath(string $path, string $mimeType): \GdImage|false
    {
        return match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : false,
            default => false,
        };
    }

    private function saveOptimizedImage(\GdImage $image, string $destinationPath): void
    {
        imagealphablending($image, true);
        imagesavealpha($image, true);

        if (str_ends_with(strtolower($destinationPath), '.webp') && function_exists('imagewebp')) {
            if (!imagewebp($image, $destinationPath, max(1, min(100, $this->webpQuality)))) {
                throw new \InvalidArgumentException('flash.message.photo_save_failed');
            }

            return;
        }

        if (!imagejpeg($image, $destinationPath, max(1, min(100, $this->webpQuality)))) {
            throw new \InvalidArgumentException('flash.message.photo_save_failed');
        }
    }
}
