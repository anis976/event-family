<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\MessagePhoto;
use App\Service\MessagePhotoService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::preRemove, entity: MessagePhoto::class)]
final class MessagePhotoCleanupListener
{
    public function __construct(
        private readonly MessagePhotoService $messagePhotoService,
    ) {
    }

    public function preRemove(MessagePhoto $photo): void
    {
        $this->messagePhotoService->deletePhotoFile($photo);
    }
}
