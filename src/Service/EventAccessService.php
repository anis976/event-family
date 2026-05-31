<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Event;
use App\Entity\Group;
use App\Entity\User;
use App\Enum\EventVisibility;

final class EventAccessService
{
    public function __construct(
        private readonly GroupAccessService $groupAccess,
        private readonly SiteStaffService $siteStaff,
    ) {
    }

    public function canView(User $user, Event $event): bool
    {
        $group = $event->getRelatedGroup();
        if (null === $group) {
            return false;
        }

        if (EventVisibility::Public === $event->getVisibility()) {
            return true;
        }

        if (!$this->groupAccess->isMember($user, $group)) {
            return false;
        }

        return !$this->groupAccess->isBannedInGroup($user, $group);
    }

    /** Chef ou modérateur du groupe, ou staff site (admin / modo). */
    public function canCreateInGroup(User $user, Group $group): bool
    {
        if ($this->siteStaff->isSiteStaff($user)) {
            return true;
        }

        if (!$this->groupAccess->isStaff($user, $group)) {
            return false;
        }

        return !$this->groupAccess->isBannedInGroup($user, $group);
    }

    /** Chef ou modérateur du groupe concerné, ou staff site. */
    public function canEdit(User $user, Event $event): bool
    {
        if ($this->siteStaff->isSiteStaff($user)) {
            return true;
        }

        $group = $event->getRelatedGroup();
        if (null === $group) {
            return false;
        }

        if (!$this->groupAccess->isStaff($user, $group)) {
            return false;
        }

        return !$this->groupAccess->isBannedInGroup($user, $group);
    }

    /** Chef du groupe concerné, ou administrateur site uniquement. */
    public function canDelete(User $user, Event $event): bool
    {
        if (\in_array(User::ROLE_ADMIN, $user->getRoles(), true)) {
            return true;
        }

        $group = $event->getRelatedGroup();

        return null !== $group && $this->groupAccess->isOwner($user, $group);
    }

    public function canContactStaff(User $user, Event $event): bool
    {
        if (!$this->canView($user, $event)) {
            return false;
        }

        $group = $event->getRelatedGroup();
        if (null === $group) {
            return false;
        }

        return !$this->groupAccess->isStaff($user, $group);
    }
}
