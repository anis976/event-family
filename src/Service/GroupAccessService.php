<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Group;
use App\Entity\GroupMember;
use App\Entity\User;
use App\Enum\GroupMemberRole;
use App\Repository\GroupMemberRepository;
use App\Repository\GroupRepository;
use App\Repository\UserBanRepository;

final class GroupAccessService
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly GroupMemberRepository $groupMemberRepository,
        private readonly UserBanRepository $userBanRepository,
    ) {
    }

    public function canCreateGroup(User $user): bool
    {
        return $this->groupRepository->countOwnedByUser($user) < 1;
    }

    public function isStaffCircle(Group $group): bool
    {
        return $group->isStaffCircle();
    }

    public function canRequestJoin(Group $group): bool
    {
        return !$group->isStaffCircle();
    }

    public function isOwner(User $user, Group $group): bool
    {
        return null !== $group->getOwner() && $group->getOwner()->getId() === $user->getId();
    }

    public function isModerator(User $user, Group $group): bool
    {
        $membership = $this->groupMemberRepository->findOneByUserAndGroup($user, $group);

        return null !== $membership && GroupMemberRole::Moderator === $membership->getRole();
    }

    public function isStaff(User $user, Group $group): bool
    {
        return $this->isOwner($user, $group) || $this->isModerator($user, $group);
    }

    public function isMember(User $user, Group $group): bool
    {
        return null !== $this->groupMemberRepository->findOneByUserAndGroup($user, $group);
    }

    public function isBannedInGroup(User $user, Group $group): bool
    {
        return null !== $this->userBanRepository->findActiveBanForUserInGroup($user, $group);
    }

    public function findMembership(User $user, Group $group): ?GroupMember
    {
        return $this->groupMemberRepository->findOneByUserAndGroup($user, $group);
    }
}
