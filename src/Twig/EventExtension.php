<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\EventPhotoSlot;
use App\Service\EventAccessService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class EventExtension extends AbstractExtension
{
    public function __construct(
        private readonly EventAccessService $eventAccess,
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('event_cover_url', $this->getCoverUrl(...)),
            new TwigFunction('event_detail_url', $this->getDetailUrl(...)),
            new TwigFunction('event_image_url', $this->getCoverUrl(...)),
            new TwigFunction('event_can_edit', $this->canEdit(...)),
            new TwigFunction('event_can_delete', $this->canDelete(...)),
            new TwigFunction('event_can_create', $this->canCreateInGroup(...)),
        ];
    }

    public function getCoverUrl(Event $event): string
    {
        return $this->getPhotoUrl($event, EventPhotoSlot::Cover);
    }

    public function getDetailUrl(Event $event): string
    {
        return $this->getPhotoUrl($event, EventPhotoSlot::Detail);
    }

    private function getPhotoUrl(Event $event, EventPhotoSlot $slot): string
    {
        $route = EventPhotoSlot::Cover === $slot ? 'app_events_photo_cover' : 'app_events_photo_detail';
        $hasPhoto = EventPhotoSlot::Cover === $slot ? $event->hasPhotoCover() : $event->hasPhotoDetail();
        $version = $hasPhoto
            ? ($event->getUpdatedAt()?->getTimestamp() ?? 0)
            : (($event->getId() ?? 0) * 10) + (EventPhotoSlot::Detail === $slot ? 1 : 0);

        return $this->urlGenerator->generate($route, [
            'id' => $event->getId(),
            'v' => $version,
        ]);
    }

    public function canEdit(Event $event, ?User $user = null): bool
    {
        $user = $this->resolveUser($user);

        return null !== $user && $this->eventAccess->canEdit($user, $event);
    }

    public function canDelete(Event $event, ?User $user = null): bool
    {
        $user = $this->resolveUser($user);

        return null !== $user && $this->eventAccess->canDelete($user, $event);
    }

    public function canCreateInGroup(Event $event, ?User $user = null): bool
    {
        $user = $this->resolveUser($user);
        $group = $event->getRelatedGroup();

        return null !== $user && null !== $group && $this->eventAccess->canCreateInGroup($user, $group);
    }

    private function resolveUser(?User $user): ?User
    {
        if (null !== $user) {
            return $user;
        }

        $current = $this->security->getUser();

        return $current instanceof User ? $current : null;
    }
}
