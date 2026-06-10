<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Message;
use App\Entity\MessagePhoto;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class MessagePhotoService
{
    public function __construct(
        private readonly MessagePhotoProcessor $imageProcessor,
        private readonly GroupAccessService $groupAccess,
        private readonly SiteStaffService $siteStaff,
        private readonly EntityManagerInterface $entityManager,
        private readonly Filesystem $filesystem,
        #[Autowire('%ef.message_photos.storage_dir%')]
        private readonly string $storageDir,
        #[Autowire('%ef.message_photos.max_bytes%')]
        private readonly int $maxBytes,
        #[Autowire('%ef.message_photos.max_per_message%')]
        private readonly int $maxPerMessage,
        #[Autowire('%ef.message_photos.caption_max_length%')]
        private readonly int $captionMaxLength,
    ) {
    }

    public function getMaxPerMessage(): int
    {
        return max(1, $this->maxPerMessage);
    }

    public function getCaptionMaxLength(): int
    {
        return max(1, $this->captionMaxLength);
    }

    public function isPhotoVisibleTo(MessagePhoto $photo, ?User $viewer): bool
    {
        $message = $photo->getMessage();
        if (!$message->isGroupMessage()) {
            return false;
        }

        $group = $message->getRelatedGroup();
        if (null === $group || null === $viewer) {
            return false;
        }

        return $this->groupAccess->isMember($viewer, $group)
            || $this->siteStaff->isSiteStaff($viewer);
    }

    public function getPhotoAbsolutePath(MessagePhoto $photo): ?string
    {
        if ('' === $photo->getFilename()) {
            return null;
        }

        $path = $this->storageDir.'/'.$photo->getFilename();

        return is_file($path) ? $path : null;
    }

    /**
     * @param list<UploadedFile> $uploadedFiles
     * @param list<array{x: int, y: int, width: int, height: int}|null> $crops
     */
    public function attachPhotosToMessage(Message $message, array $uploadedFiles, array $crops = []): void
    {
        if (!$message->isGroupMessage() || null !== $message->getParent()) {
            throw new \DomainException('flash.message.photo_group_root_only');
        }

        $uploadedFiles = array_values(array_filter(
            $uploadedFiles,
            static fn (?UploadedFile $file): bool => $file instanceof UploadedFile && $file->isValid(),
        ));

        if ([] === $uploadedFiles) {
            return;
        }

        if (\count($uploadedFiles) > $this->getMaxPerMessage()) {
            throw new \InvalidArgumentException('flash.message.photo_max_count');
        }

        $this->filesystem->mkdir($this->storageDir);

        $createdFiles = [];

        try {
            foreach ($uploadedFiles as $index => $uploadedFile) {
                $this->assertValidUpload($uploadedFile);

                $mimeType = $uploadedFile->getMimeType() ?? '';
                if (null === $this->imageProcessor->resolveExtensionForMime($mimeType)) {
                    throw new \InvalidArgumentException('flash.message.photo_invalid_format');
                }

                $filename = bin2hex(random_bytes(16)).'.webp';
                $destination = $this->storageDir.'/'.$filename;
                $crop = $crops[$index] ?? null;

                $this->imageProcessor->processAndSave(
                    $uploadedFile->getPathname(),
                    $destination,
                    $mimeType,
                    $crop,
                );
                $createdFiles[] = $destination;

                $photo = (new MessagePhoto())
                    ->setFilename($filename)
                    ->setPosition($index);

                $message->addPhoto($photo);
                $this->entityManager->persist($photo);
            }
        } catch (\Throwable $e) {
            foreach ($createdFiles as $path) {
                if (is_file($path)) {
                    $this->filesystem->remove($path);
                }
            }

            throw $e;
        }
    }

    public function deletePhotoFile(MessagePhoto $photo): void
    {
        $path = $this->getPhotoAbsolutePath($photo);
        if (null !== $path) {
            $this->filesystem->remove($path);
        }
    }

    public function deletePhotosForMessage(Message $message): void
    {
        foreach ($message->getPhotos()->toArray() as $photo) {
            $this->deletePhotoFile($photo);
        }
    }

    /**
     * @param list<UploadedFile> $uploadedFiles
     */
    public function validateGroupMessagePayload(string $content, array $uploadedFiles): void
    {
        $uploadedFiles = array_values(array_filter(
            $uploadedFiles,
            static fn (?UploadedFile $file): bool => $file instanceof UploadedFile && $file->isValid(),
        ));

        $trimmedContent = trim($content);

        if ('' === $trimmedContent && [] === $uploadedFiles) {
            throw new \InvalidArgumentException('flash.message.content_or_photo_required');
        }

        if ([] !== $uploadedFiles && mb_strlen($trimmedContent) > $this->getCaptionMaxLength()) {
            throw new \InvalidArgumentException('flash.message.photo_caption_max');
        }

        if (\count($uploadedFiles) > $this->getMaxPerMessage()) {
            throw new \InvalidArgumentException('flash.message.photo_max_count');
        }
    }

    private function assertValidUpload(UploadedFile $uploadedFile): void
    {
        if (!$uploadedFile->isValid()) {
            throw new \InvalidArgumentException('flash.message.photo_invalid_file');
        }

        if ($uploadedFile->getSize() > $this->maxBytes) {
            throw new \InvalidArgumentException('flash.message.photo_max_size');
        }
    }
}
