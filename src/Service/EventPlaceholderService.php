<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Event;
use App\Enum\EventPhotoSlot;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class EventPlaceholderService
{
    private const DEFAULT_REMOTE = 'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?w=800&q=80&auto=format&fit=crop';

    /** @param list<string> $remotePlaceholders */
    public function __construct(
        #[Autowire('%kernel.project_dir%/assets/images/event-placeholders')]
        private readonly string $localDir,
        #[Autowire('%ef.events.placeholders%')]
        private readonly array $remotePlaceholders,
    ) {
    }

    public function getLocalAbsolutePath(Event $event, EventPhotoSlot $slot = EventPhotoSlot::Cover): ?string
    {
        $filename = $this->getLocalFilename($event, $slot);
        $path = $this->localDir.'/'.$filename;

        return is_file($path) ? $path : null;
    }

    public function getRemoteUrlForEvent(Event $event, EventPhotoSlot $slot = EventPhotoSlot::Cover): string
    {
        $items = $this->remotePlaceholders;
        if ([] === $items) {
            return self::DEFAULT_REMOTE;
        }

        $index = $this->resolveIndex($event, $slot, \count($items));

        return $items[$index];
    }

    /** @deprecated Utiliser getRemoteUrlForEvent() ou getLocalAbsolutePath() */
    public function getUrlForEvent(Event $event, EventPhotoSlot $slot = EventPhotoSlot::Cover): string
    {
        return $this->getRemoteUrlForEvent($event, $slot);
    }

    private function getLocalFilename(Event $event, EventPhotoSlot $slot): string
    {
        $count = $this->countLocalFiles();
        $index = $this->resolveIndex($event, $slot, max(1, $count));

        return sprintf('%02d.jpg', $index + 1);
    }

    private function countLocalFiles(): int
    {
        if (!is_dir($this->localDir)) {
            return 0;
        }

        $files = glob($this->localDir.'/*.jpg') ?: [];

        return \count($files);
    }

    private function resolveIndex(Event $event, EventPhotoSlot $slot, int $count): int
    {
        if ($count <= 0) {
            return 0;
        }

        $baseIndex = ($event->getId() ?? 0) % $count;

        return EventPhotoSlot::Detail === $slot
            ? ($baseIndex + (int) floor($count / 2)) % $count
            : $baseIndex;
    }
}
