<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AvatarImageProcessor
{
    private const array ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        #[Autowire('%ef.avatar.output_size%')]
        private readonly int $outputSize,
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
     * @param array{x: int, y: int, width: int, height: int} $crop
     */
    public function cropAndSaveSquare(
        string $sourcePath,
        string $destinationPath,
        array $crop,
        string $mimeType,
    ): void {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('L\'extension PHP GD est requise pour traiter les avatars.');
        }

        $image = $this->createImageFromPath($sourcePath, $mimeType);
        if (false === $image) {
            throw new \InvalidArgumentException('Impossible de lire l\'image source.');
        }

        $crop = $this->normalizeCrop($crop, imagesx($image), imagesy($image));

        $cropped = imagecrop($image, $crop);
        imagedestroy($image);

        if (false === $cropped) {
            throw new \InvalidArgumentException('Recadrage invalide.');
        }

        $size = max(1, $this->outputSize);
        $resized = imagecreatetruecolor($size, $size);

        if (false === $resized) {
            imagedestroy($cropped);
            throw new \RuntimeException('Impossible de redimensionner l\'avatar.');
        }

        imagealphablending($resized, false);
        imagesavealpha($resized, true);

        $sourceWidth = imagesx($cropped);
        $sourceHeight = imagesy($cropped);

        if (!imagecopyresampled(
            $resized,
            $cropped,
            0,
            0,
            0,
            0,
            $size,
            $size,
            $sourceWidth,
            $sourceHeight,
        )) {
            imagedestroy($cropped);
            imagedestroy($resized);
            throw new \RuntimeException('Échec du redimensionnement de l\'avatar.');
        }

        imagedestroy($cropped);

        $this->saveOptimizedImage($resized, $destinationPath);
        imagedestroy($resized);
    }

    /**
     * @param array{x: int, y: int, width: int, height: int} $crop
     */
    public function regenerateFromOriginal(
        string $originalPath,
        string $destinationPath,
        array $crop,
        string $mimeType,
    ): void {
        $this->cropAndSaveSquare($originalPath, $destinationPath, $crop, $mimeType);
    }

    /**
     * @param array{x: int, y: int, width: int, height: int} $crop
     *
     * @return array{x: int, y: int, width: int, height: int}
     */
    private function normalizeCrop(array $crop, int $maxWidth, int $maxHeight): array
    {
        $x = max(0, min((int) $crop['x'], $maxWidth - 1));
        $y = max(0, min((int) $crop['y'], $maxHeight - 1));
        $width = max(1, min((int) $crop['width'], $maxWidth - $x));
        $height = max(1, min((int) $crop['height'], $maxHeight - $y));
        $side = min($width, $height);

        return [
            'x' => $x,
            'y' => $y,
            'width' => $side,
            'height' => $side,
        ];
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
        if (str_ends_with(strtolower($destinationPath), '.webp') && function_exists('imagewebp')) {
            if (!imagewebp($image, $destinationPath, 85)) {
                throw new \RuntimeException('Impossible d\'enregistrer l\'avatar.');
            }

            return;
        }

        if (!imagejpeg($image, $destinationPath, 88)) {
            throw new \RuntimeException('Impossible d\'enregistrer l\'avatar.');
        }
    }
}
