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
        $this->assertUserCanBecomeOwner($owner, $group->getId());

        $group->setOwner($owner);

        $membership = $this->resolveOrCreateMembership($group, $owner, true);
        $membership->setRole(GroupMemberRole::Owner);
        $this->demoteOtherOwnerRoles($group, $owner);
    }

    /**
     * Réassignation depuis l'admin : le nouveau chef est ajouté au groupe s'il n'y est pas encore.
     *
     * @throws \DomainException
     */
    public function assignOwnerFromAdmin(Group $group, User $newOwner, ?User $previousOwner): void
    {
        $this->releaseOtherOwnedGroups($newOwner, $group->getId());

        $this->transferOwnership(
            $group,
            $newOwner,
            $previousOwner,
            GroupMemberRole::Member,
            ensureMembership: true,
        );
    }

    /**
     * Création / réassignation admin : libère les autres groupes dirigés par le même compte.
     *
     * @throws \DomainException
     */
    public function bindOwnerOnGroupCreateFromAdmin(Group $group, User $owner): void
    {
        $this->releaseOtherOwnedGroups($owner, $group->getId());
        $this->bindOwnerOnGroupCreate($group, $owner);
    }

    /**
     * @throws \DomainException
     */
    public function transferOwnership(
        Group $group,
        User $newOwner,
        ?User $previousOwner,
        GroupMemberRole $previousOwnerNewRole = GroupMemberRole::Member,
        bool $ensureMembership = false,
    ): void {
        if (null !== $previousOwner && $previousOwner->getId() === $newOwner->getId()) {
            return;
        }

        if ($this->groupAccess->isBannedInGroup($newOwner, $group)) {
            throw new \DomainException('admin.crud.group.error_owner_banned');
        }

        $this->assertUserCanBecomeOwner($newOwner, $group->getId());

        $group->setOwner($newOwner);

        $newMembership = $this->resolveOrCreateMembership($group, $newOwner, $ensureMembership);
        $newMembership->setRole(GroupMemberRole::Owner);

        if (null !== $previousOwner && $previousOwner->getId() !== $newOwner->getId()) {
            $previousMembership = $this->groupMemberRepository->findOneByUserAndGroup($previousOwner, $group);
            if (null !== $previousMembership && GroupMemberRole::Owner === $previousMembership->getRole()) {
                if (GroupMemberRole::Moderator === $previousOwnerNewRole) {
                    $this->demoteOtherModerators($group, $previousOwner);
                    $previousMembership->setRole(GroupMemberRole::Moderator);
                } else {
                    $previousMembership->setRole(GroupMemberRole::Member);
                }
            }
        }

        $this->demoteOtherOwnerRoles($group, $newOwner);
    }

    /**
     * Transfert volontaire par le chef actuel (confirmation mot de passe côté contrôleur).
     *
     * @throws \DomainException clés flash.group.*
     */
    public function transferOwnershipByCurrentOwner(
        User $currentOwner,
        Group $group,
        User $newOwner,
        bool $becomeModeratorAfterTransfer,
    ): void {
        if (!$this->groupAccess->isOwner($currentOwner, $group)) {
            throw new \DomainException('flash.group.owner_only_transfer');
        }

        if ($currentOwner->getId() === $newOwner->getId()) {
            throw new \DomainException('flash.group.transfer_self');
        }

        try {
            $this->transferOwnership(
                $group,
                $newOwner,
                $currentOwner,
                $becomeModeratorAfterTransfer ? GroupMemberRole::Moderator : GroupMemberRole::Member,
            );
        } catch (\DomainException $exception) {
            throw new \DomainException($this->mapOwnershipErrorToFlashKey($exception->getMessage()));
        }

        $this->entityManager->flush();
    }

    /**
     * Dissout un groupe dont l'utilisateur est le seul membre (ex. avant suppression de compte).
     *
     * @throws \DomainException
     */
    public function dissolveGroupAsOwner(User $owner, Group $group): void
    {
        if (!$this->groupAccess->isOwner($owner, $group)) {
            throw new \DomainException('flash.group.owner_only_dissolve');
        }

        if ($this->groupMemberRepository->countOtherMembersInGroup($group, $owner) > 0) {
            throw new \DomainException('flash.group.dissolve_requires_sole_member');
        }

        $this->entityManager->remove($group);
        $this->entityManager->flush();
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
     * Vérifie qu'un utilisateur peut devenir chef (un seul groupe par compte).
     *
     * @throws \DomainException
     */
    public function assertNewOwnerCanLeadGroup(User $user, ?int $forGroupId): void
    {
        $this->assertUserCanBecomeOwner($user, $forGroupId);
    }

    /**
     * @throws \DomainException
     */
    private function assertUserCanBecomeOwner(User $user, ?int $forGroupId): void
    {
        if (null !== $user->getDeletedAt()) {
            throw new \DomainException('admin.crud.group.error_owner_deleted');
        }

        if ($this->groupRepository->countOwnedByUserExcludingGroup($user, $forGroupId) > 0) {
            throw new \DomainException('admin.crud.group.error_owner_already_leads');
        }
    }

    private function mapOwnershipErrorToFlashKey(string $message): string
    {
        return match ($message) {
            'admin.crud.group.error_owner_not_member' => 'flash.group.transfer_target_not_member',
            'admin.crud.group.error_owner_already_leads' => 'flash.group.transfer_target_already_owner',
            'admin.crud.group.error_owner_deleted' => 'flash.group.transfer_target_deleted',
            'admin.crud.group.error_owner_banned' => 'flash.group.transfer_target_banned',
            default => $message,
        };
    }

    private function demoteOtherModerators(Group $group, User $exceptUser): void
    {
        foreach ($this->groupMemberRepository->findModeratorsInGroupExcluding($group, $exceptUser) as $member) {
            $member->setRole(GroupMemberRole::Member);
        }
    }

    private function demoteOtherOwnerRoles(Group $group, User $newOwner): void
    {
        foreach ($this->groupMemberRepository->findOwnerMembersInGroupExcluding($group, $newOwner) as $member) {
            $member->setRole(GroupMemberRole::Member);
        }
    }

    private function releaseOtherOwnedGroups(User $user, ?int $exceptGroupId): void
    {
        foreach ($this->groupRepository->findOwnedByUser($user) as $otherGroup) {
            if (null !== $exceptGroupId && $otherGroup->getId() === $exceptGroupId) {
                continue;
            }

            $this->clearOwnership($otherGroup, $user);
        }
    }

    /**
     * @throws \DomainException
     */
    private function resolveOrCreateMembership(Group $group, User $user, bool $createIfMissing): GroupMember
    {
        $membership = $this->groupMemberRepository->findOneByUserAndGroup($user, $group);
        if (null !== $membership) {
            return $membership;
        }

        if (!$createIfMissing) {
            throw new \DomainException('admin.crud.group.error_owner_not_member');
        }

        $membership = (new GroupMember())
            ->setUser($user)
            ->setGroup($group);
        $group->addGroupMember($membership);
        $this->entityManager->persist($membership);

        return $membership;
    }
}
