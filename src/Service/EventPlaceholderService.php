<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Event;
use App\Enum\EventPhotoSlot;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class EventPlaceholderService
{
    /** @param list<string> $placeholders */
    public function __construct(
        #[Autowire('%ef.events.placeholders%')]
        private readonly array $placeholders,
    ) {
    }

    public function getUrlForEvent(Event $event, EventPhotoSlot $slot = EventPhotoSlot::Cover): string
    {
        $items = $this->placeholders;
        if ([] === $items) {
            return 'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?w=800&q=80&auto=format&fit=crop';
        }

        $count = \count($items);
        $baseIndex = ($event->getId() ?? 0) % $count;
        $index = EventPhotoSlot::Detail === $slot
            ? ($baseIndex + (int) floor($count / 2)) % $count
            : $baseIndex;

        return $items[$index];
    }
}
