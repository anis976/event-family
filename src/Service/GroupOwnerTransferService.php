<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Group;
use App\Entity\GroupMember;
use App\Entity\User;
use App\Enum\GroupMemberRole;
use App\Repository\GroupMemberRepository;
use App\Repository\GroupRepository;
use Doctrine\ORM\EntityManagerInterface;

final class GroupOwnerTransferService
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly GroupMemberRepository $groupMemberRepository,
        private readonly GroupAccessService $groupAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findSuggestedSuccessor(Group $group, User $departingOwner): ?User
    {
        foreach ($this->groupMemberRepository->findOtherMembersOrdered($group, $departingOwner) as $member) {
            if (GroupMemberRole::Moderator === $member->getRole()) {
                return $member->getUser();
            }
        }

        $others = $this->groupMemberRepository->findOtherMembersOrdered($group, $departingOwner);

        return $others[0]?->getUser();
    }

    public function hasOtherMembers(Group $group, User $owner): bool
    {
        return $this->groupMemberRepository->countOtherMembersInGroup($group, $owner) > 0;
    }

    /**
     * @throws \DomainException
     */
    public function bindOwnerOnGroupCreate(Group $group, User $owner): void
    {
        $this->assertUserCanBecomeOwner($owner, $group);

        $group->setOwner($owner);

        $membership = $this->groupMemberRepository->findOneByUserAndGroup($owner, $group);
        if (null === $membership) {
            $membership = (new GroupMember())
                ->setUser($owner)
                ->setGroup($group);
            $group->addGroupMember($membership);
            $this->entityManager->persist($membership);
        }

        $membership->setRole(GroupMemberRole::Owner);
        $this->demoteOtherOwnerRoles($group, $owner);
    }

    /**
     * @throws \DomainException
     */
    public function transferOwnership(Group $group, User $newOwner, ?User $previousOwner): void
    {
        if (null !== $previousOwner && $previousOwner->getId() === $newOwner->getId()) {
            return;
        }

        if (null === $this->groupMemberRepository->findOneByUserAndGroup($newOwner, $group)) {
            throw new \DomainException('admin.crud.group.error_owner_not_member');
        }

        $this->assertUserCanBecomeOwner($newOwner, $group);

        $group->setOwner($newOwner);

        $newMembership = $this->groupMemberRepository->findOneByUserAndGroup($newOwner, $group);
        $newMembership?->setRole(GroupMemberRole::Owner);

        if (null !== $previousOwner && $previousOwner->getId() !== $newOwner->getId()) {
            $previousMembership = $this->groupMemberRepository->findOneByUserAndGroup($previousOwner, $group);
            if (null !== $previousMembership && GroupMemberRole::Owner === $previousMembership->getRole()) {
                $previousMembership->setRole(GroupMemberRole::Member);
            }
        }

        $this->demoteOtherOwnerRoles($group, $newOwner);
    }

    public function clearOwnership(Group $group, ?User $previousOwner): void
    {
        $group->setOwner(null);

        if (null !== $previousOwner) {
            $previousMembership = $this->groupMemberRepository->findOneByUserAndGroup($previousOwner, $group);
            if (null !== $previousMembership && GroupMemberRole::Owner === $previousMembership->getRole()) {
                $previousMembership->setRole(GroupMemberRole::Member);
            }
        }

        foreach ($group->getGroupMembers() as $member) {
            if (GroupMemberRole::Owner === $member->getRole()) {
                $member->setRole(GroupMemberRole::Member);
            }
        }
    }

    /**
     * @throws \DomainException
     */
    private function assertUserCanBecomeOwner(User $user, Group $group): void
    {
        if (null !== $user->getDeletedAt()) {
            throw new \DomainException('admin.crud.group.error_owner_deleted');
        }

        if ($this->groupRepository->countOwnedByUser($user) > 0 && !$this->groupAccess->isOwner($user, $group)) {
            throw new \DomainException('admin.crud.group.error_owner_already_leads');
        }
    }

    private function demoteOtherOwnerRoles(Group $group, User $newOwner): void
    {
        foreach ($group->getGroupMembers() as $member) {
            if ($member->getUser()->getId() === $newOwner->getId()) {
                continue;
            }

            if (GroupMemberRole::Owner === $member->getRole()) {
                $member->setRole(GroupMemberRole::Member);
            }
        }
    }
}
