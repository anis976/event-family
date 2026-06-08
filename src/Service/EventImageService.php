<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Event;
use App\Enum\EventPhotoSlot;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class EventImageService
{
    public function __construct(
        private readonly EventImageProcessor $imageProcessor,
        private readonly Filesystem $filesystem,
        #[Autowire('%ef.events.storage_dir%')]
        private readonly string $storageDir,
        #[Autowire('%ef.events.max_bytes%')]
        private readonly int $maxBytes,
    ) {
    }

    public function getPhotoAbsolutePath(Event $event, EventPhotoSlot $slot): ?string
    {
        $filename = $this->getFilename($event, $slot);
        if (null === $filename) {
            return null;
        }

        $path = $this->storageDir.'/'.$filename;

        return is_file($path) ? $path : null;
    }

    public function storeUploadedPhoto(Event $event, UploadedFile $uploadedFile, EventPhotoSlot $slot): void
    {
        if (!$uploadedFile->isValid()) {
            throw new \InvalidArgumentException('flash.event.image_invalid_file');
        }

        if ($uploadedFile->getSize() > $this->maxBytes) {
            throw new \InvalidArgumentException('flash.event.image_max_size');
        }

        $mimeType = $uploadedFile->getMimeType() ?? '';
        $extension = $this->imageProcessor->resolveExtensionForMime($mimeType);
        if (null === $extension) {
            throw new \InvalidArgumentException('flash.event.image_invalid_format');
        }

        $this->filesystem->mkdir($this->storageDir);
        $this->removePhotoFile($event, $slot);

        $filename = bin2hex(random_bytes(16)).'.'.$extension;
        $destination = $this->storageDir.'/'.$filename;

        $this->imageProcessor->resizeAndSave($uploadedFile->getPathname(), $destination, $mimeType);
        $this->setFilename($event, $slot, $filename);
    }

    public function removePhoto(Event $event, EventPhotoSlot $slot): void
    {
        $this->removePhotoFile($event, $slot);
        $this->setFilename($event, $slot, null);
    }

    public function deleteEventFiles(Event $event): void
    {
        $this->removePhotoFile($event, EventPhotoSlot::Cover);
        $this->removePhotoFile($event, EventPhotoSlot::Detail);
    }

    private function getFilename(Event $event, EventPhotoSlot $slot): ?string
    {
        return match ($slot) {
            EventPhotoSlot::Cover => $event->getPhotoCover(),
            EventPhotoSlot::Detail => $event->getPhotoDetail(),
        };
    }

    private function setFilename(Event $event, EventPhotoSlot $slot, ?string $filename): void
    {
        match ($slot) {
            EventPhotoSlot::Cover => $event->setPhotoCover($filename),
            EventPhotoSlot::Detail => $event->setPhotoDetail($filename),
        };
    }

    private function removePhotoFile(Event $event, EventPhotoSlot $slot): void
    {
        $filename = $this->getFilename($event, $slot);
        if (null === $filename || '' === $filename) {
            return;
        }

        $path = $this->storageDir.'/'.$filename;
        if (is_file($path)) {
            $this->filesystem->remove($path);
        }
    }
}
