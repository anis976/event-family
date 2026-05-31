<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\AvatarVisibility;
use App\Repository\GroupRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Filesystem\Filesystem;

final class UserAvatarService
{
    public function __construct(
        private readonly AvatarImageProcessor $imageProcessor,
        private readonly GroupRepository $groupRepository,
        private readonly Filesystem $filesystem,
        #[Autowire('%ef.avatar.storage_dir%')]
        private readonly string $storageDir,
        #[Autowire('%ef.avatar.max_bytes%')]
        private readonly int $maxBytes,
    ) {
    }

    public function isAvatarVisibleTo(User $profileUser, ?User $viewer): bool
    {
        if (!$profileUser->hasAvatar()) {
            return false;
        }

        if (null !== $viewer && $viewer->getId() === $profileUser->getId()) {
            return true;
        }

        $visibility = $profileUser->getAvatarVisibility() ?? AvatarVisibility::Private;

        if (AvatarVisibility::Public === $visibility) {
            return null !== $viewer;
        }

        if (null === $viewer) {
            return false;
        }

        return $this->groupRepository->usersShareAtLeastOneGroup($profileUser, $viewer);
    }

    public function getAvatarAbsolutePath(User $user): ?string
    {
        if (!$user->hasAvatar()) {
            return null;
        }

        $path = $this->storageDir.'/'.$user->getAvatar();

        return is_file($path) ? $path : null;
    }

    /**
     * @param array{x: int, y: int, width: int, height: int} $crop
     */
    public function storeUploadedAvatar(
        User $user,
        UploadedFile $uploadedFile,
        AvatarVisibility $visibility,
        array $crop,
    ): void {
        if (!$uploadedFile->isValid()) {
            throw new \InvalidArgumentException('Fichier invalide.');
        }

        if ($uploadedFile->getSize() > $this->maxBytes) {
            throw new \InvalidArgumentException('La photo ne doit pas dépasser 4 Mo.');
        }

        $mimeType = $uploadedFile->getMimeType() ?? '';
        $extension = $this->imageProcessor->resolveExtensionForMime($mimeType);

        if (null === $extension) {
            throw new \InvalidArgumentException('Format non autorisé. Utilise JPG, PNG ou WebP.');
        }

        if (!$this->isValidImageContent($uploadedFile->getPathname(), $mimeType)) {
            throw new \InvalidArgumentException('Le fichier n\'est pas une image valide.');
        }

        $this->ensureStorageDir();
        $this->deleteAvatarFiles($user);

        $uuid = bin2hex(random_bytes(16));
        $originalFilename = sprintf('%s_orig.%s', $uuid, $extension);
        $avatarFilename = function_exists('imagewebp')
            ? sprintf('%s_avatar.webp', $uuid)
            : sprintf('%s_avatar.jpg', $uuid);

        $uploadedFile->move($this->storageDir, $originalFilename);

        $originalPath = $this->storageDir.'/'.$originalFilename;
        $avatarPath = $this->storageDir.'/'.$avatarFilename;

        try {
            $this->imageProcessor->cropAndSaveSquare($originalPath, $avatarPath, $crop, $mimeType);
        } catch (\Throwable $e) {
            $this->filesystem->remove($originalPath);
            throw $e;
        }

        $user->setAvatarOriginal($originalFilename);
        $user->setAvatar($avatarFilename);
        $user->setAvatarVisibility($visibility);
        $user->setAvatarCropData($crop);
    }

    public function deleteAvatar(User $user): void
    {
        if (!$user->hasAvatar() && null === $user->getAvatarOriginal()) {
            $user->clearAvatar();

            return;
        }

        $this->deleteAvatarFiles($user);
        $user->clearAvatar();
    }

    public function deleteAvatarFiles(User $user): void
    {
        $filenames = array_values(array_unique(array_filter([
            $user->getAvatar(),
            $user->getAvatarOriginal(),
        ], static fn (?string $name): bool => null !== $name && '' !== $name)));

        foreach ($filenames as $filename) {
            $this->assertSafeFilename($filename);
            $path = $this->storageDir.'/'.$filename;

            if (is_file($path)) {
                $this->filesystem->remove($path);
            }
        }
    }

    private function ensureStorageDir(): void
    {
        if (!$this->filesystem->exists($this->storageDir)) {
            $this->filesystem->mkdir($this->storageDir, 0755);
        }
    }

    private function isValidImageContent(string $path, string $expectedMime): bool
    {
        $info = @getimagesize($path);
        if (false === $info) {
            return false;
        }

        $detected = $info['mime'] ?? '';

        return \in_array($detected, $this->imageProcessor->allowedMimeTypes(), true)
            && $detected === $expectedMime;
    }

    private function assertSafeFilename(string $filename): void
    {
        if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
            throw new \InvalidArgumentException('Nom de fichier avatar invalide.');
        }
    }
}
