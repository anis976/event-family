<?php

declare(strict_types=1);

namespace App\Service;

final class EventImageProcessor
{
    private const array ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private const int MAX_WIDTH = 1200;

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

    public function resizeAndSave(string $sourcePath, string $destinationPath, string $mimeType): void
    {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('L\'extension PHP GD est requise pour traiter les photos d\'événements.');
        }

        $image = $this->createImageFromPath($sourcePath, $mimeType);
        if (false === $image) {
            throw new \InvalidArgumentException('Impossible de lire l\'image source.');
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width > self::MAX_WIDTH) {
            $newWidth = self::MAX_WIDTH;
            $newHeight = (int) round($height * ($newWidth / $width));
            $resized = imagescale($image, $newWidth, $newHeight);
            imagedestroy($image);
            if (false === $resized) {
                throw new \InvalidArgumentException('Impossible de redimensionner l\'image.');
            }
            $image = $resized;
        }

        $this->saveImage($image, $destinationPath, $mimeType);
        imagedestroy($image);
    }

    /**
     * @return \GdImage|false
     */
    private function createImageFromPath(string $path, string $mimeType): \GdImage|false
    {
        return match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/webp' => imagecreatefromwebp($path),
            default => false,
        };
    }

    private function saveImage(\GdImage $image, string $path, string $mimeType): void
    {
        $saved = match ($mimeType) {
            'image/jpeg' => imagejpeg($image, $path, 85),
            'image/png' => imagepng($image, $path, 6),
            'image/webp' => imagewebp($image, $path, 85),
            default => false,
        };

        if (false === $saved) {
            throw new \InvalidArgumentException('Impossible d\'enregistrer l\'image.');
        }
    }
}
