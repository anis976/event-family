<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Group;
use App\Entity\GroupMember;
use App\Entity\User;
use App\Entity\UserBan;
use App\Enum\GroupMemberRole;
use App\Repository\GroupMemberRepository;
use App\Repository\UserBanRepository;
use App\Util\ParisClock;
use Doctrine\ORM\EntityManagerInterface;

final class GroupMemberModerationService
{
    public function __construct(
        private readonly GroupAccessService $groupAccess,
        private readonly GroupMemberRepository $groupMemberRepository,
        private readonly UserBanRepository $userBanRepository,
        private readonly BanEscalationService $banEscalation,
        private readonly StaffCircleService $staffCircleService,
        private readonly GroupOwnerTransferService $groupOwnerTransfer,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function toggleModerator(User $actor, GroupMember $targetMember): void
    {
        $group = $targetMember->getGroup();

        if (!$this->groupAccess->isOwner($actor, $group)) {
            throw new \DomainException('flash.group.owner_only_moderator');
        }

        $this->assertCanModerateTarget($actor, $targetMember);

        if (GroupMemberRole::Owner === $targetMember->getRole()) {
            throw new \DomainException('flash.group.owner_cannot_change');
        }

        if (GroupMemberRole::Moderator === $targetMember->getRole()) {
            $targetMember->setRole(GroupMemberRole::Member);
            $this->entityManager->flush();
            $this->staffCircleService->syncUser($targetMember->getUser());

            return;
        }

        if ($this->groupMemberRepository->countModeratorsInGroup($group) >= 1) {
            throw new \DomainException('flash.group.single_moderator');
        }

        $targetMember->setRole(GroupMemberRole::Moderator);
        $this->entityManager->flush();
        $this->staffCircleService->syncUser($targetMember->getUser());
    }

    /**
     * Désigne le modérateur depuis l'admin (un seul modérateur : l'ancien perd le titre).
     *
     * @throws \DomainException
     */
    public function assignModeratorFromAdmin(Group $group, User $newModerator): void
    {
        $membership = $this->groupMemberRepository->findOneByUserAndGroup($newModerator, $group);
        if (null === $membership) {
            throw new \DomainException('admin.crud.group.error_moderator_not_member');
        }

        if (GroupMemberRole::Owner === $membership->getRole()) {
            throw new \DomainException('admin.crud.group.error_moderator_is_owner');
        }

        if ($this->groupAccess->isBannedInGroup($newModerator, $group)) {
            throw new \DomainException('admin.crud.group.error_moderator_banned');
        }

        $demotedUsers = [];
        foreach ($this->groupMemberRepository->findModeratorsInGroupExcluding($group, $newModerator) as $moderator) {
            $moderator->setRole(GroupMemberRole::Member);
            $demotedUsers[] = $moderator->getUser();
        }

        if (GroupMemberRole::Moderator !== $membership->getRole()) {
            $membership->setRole(GroupMemberRole::Moderator);
        }

        $this->entityManager->flush();

        $this->staffCircleService->syncUser($newModerator);
        foreach ($demotedUsers as $demotedUser) {
            $this->staffCircleService->syncUser($demotedUser);
        }
    }

    /**
     * Retire le rôle modérateur (admin).
     *
     * @throws \DomainException
     */
    public function clearModeratorFromAdmin(Group $group, GroupMember $targetMember): void
    {
        if ($targetMember->getGroup()->getId() !== $group->getId()) {
            throw new \DomainException('admin.crud.group.error_moderator_wrong_group');
        }

        if (GroupMemberRole::Moderator !== $targetMember->getRole()) {
            throw new \DomainException('admin.crud.group.error_moderator_not_moderator');
        }

        $targetMember->setRole(GroupMemberRole::Member);
        $this->entityManager->flush();
        $this->staffCircleService->syncUser($targetMember->getUser());
    }

    public function banMember(User $actor, GroupMember $targetMember, string $reason): void
    {
        $group = $targetMember->getGroup();

        if (!$this->groupAccess->isStaff($actor, $group)) {
            throw new \DomainException('flash.group.staff_only_ban');
        }

        $this->assertCanModerateTarget($actor, $targetMember);
        $this->assertStaffCanActOnTargetRole($actor, $group, $targetMember);

        if (null !== $this->userBanRepository->findActiveBanForUserInGroup($targetMember->getUser(), $group)) {
            throw new \DomainException('flash.group.already_banned');
        }

        $trimmedReason = trim($reason);
        if ('' === $trimmedReason) {
            throw new \DomainException('flash.group.ban_reason_required_service');
        }

        $ban = (new UserBan())
            ->setBannedUser($targetMember->getUser())
            ->setRelatedGroup($group)
            ->setAuthor($actor)
            ->setReason($trimmedReason);

        $this->entityManager->persist($ban);
        $this->entityManager->flush();

        $this->banEscalation->handleAfterGroupBan($ban);
    }

    public function unbanMember(User $actor, GroupMember $targetMember): void
    {
        $group = $targetMember->getGroup();

        if (!$this->groupAccess->isStaff($actor, $group)) {
            throw new \DomainException('flash.group.staff_only_unban');
        }

        $ban = $this->userBanRepository->findActiveBanForUserInGroup($targetMember->getUser(), $group);
        if (null === $ban) {
            throw new \DomainException('flash.group.not_banned');
        }

        $ban->setEndsAt(ParisClock::now());
        $this->entityManager->flush();
    }

    public function kickMember(User $actor, GroupMember $targetMember): void
    {
        $group = $targetMember->getGroup();

        if (!$this->groupAccess->isOwner($actor, $group)) {
            throw new \DomainException('flash.group.owner_only_kick');
        }

        $this->assertCanModerateTarget($actor, $targetMember);

        if (GroupMemberRole::Owner === $targetMember->getRole()) {
            throw new \DomainException('flash.group.owner_cannot_kick');
        }

        $this->removeMembership($targetMember);
    }

    /**
     * Retrait d'un membre depuis l'admin.
     *
     * @throws \DomainException
     */
    public function removeMemberFromAdmin(Group $group, GroupMember $targetMember): void
    {
        if ($targetMember->getGroup()->getId() !== $group->getId()) {
            throw new \DomainException('admin.crud.group.error_member_wrong_group');
        }

        if ($this->groupAccess->isOwner($targetMember->getUser(), $group)) {
            throw new \DomainException('admin.crud.group.error_cannot_remove_owner');
        }

        $this->removeMembership($targetMember);
    }

    /**
     * Quitter un groupe (membre ou modérateur). Le chef seul dissout le groupe.
     *
     * @throws \DomainException
     */
    public function leaveGroup(User $user, Group $group): void
    {
        if ($group->isStaffCircle()) {
            throw new \DomainException('flash.group.staff_circle_no_leave');
        }

        if (!$this->groupAccess->isMember($user, $group)) {
            throw new \DomainException('flash.group.not_member');
        }

        if ($this->groupAccess->isOwner($user, $group)) {
            if ($this->groupOwnerTransfer->hasOtherMembers($group, $user)) {
                throw new \DomainException('flash.group.owner_must_designate_successor');
            }

            $this->groupOwnerTransfer->dissolveGroupAsOwner($user, $group);

            return;
        }

        $membership = $this->groupMemberRepository->findOneByUserAndGroup($user, $group);
        if (null === $membership) {
            throw new \DomainException('flash.group.not_member');
        }

        $this->removeMembership($membership);
    }

    /**
     * Le chef quitte le groupe après avoir désigné un successeur.
     *
     * @throws \DomainException
     */
    public function leaveGroupAsOwner(User $owner, Group $group, User $successor): void
    {
        if ($group->isStaffCircle()) {
            throw new \DomainException('flash.group.staff_circle_no_leave');
        }

        if (!$this->groupAccess->isOwner($owner, $group)) {
            throw new \DomainException('flash.group.owner_only_leave');
        }

        if ($owner->getId() === $successor->getId()) {
            throw new \DomainException('flash.group.transfer_self');
        }

        $this->groupOwnerTransfer->reconcileOwnerRoles($group);

        $this->groupOwnerTransfer->transferOwnership(
            $group,
            $successor,
            $owner,
            GroupMemberRole::Member,
        );

        $ownerMembership = $this->groupMemberRepository->findOneByUserAndGroup($owner, $group);
        if (null !== $ownerMembership) {
            $this->removeMembership($ownerMembership, false);
        }

        $this->entityManager->flush();
        $this->staffCircleService->syncUser($owner);
    }

    private function removeMembership(GroupMember $targetMember, bool $flush = true): void
    {
        $group = $targetMember->getGroup();
        $targetUser = $targetMember->getUser();

        $activeBan = $this->userBanRepository->findActiveBanForUserInGroup($targetUser, $group);
        if (null !== $activeBan) {
            $activeBan->setEndsAt(ParisClock::now());
        }

        $group->removeGroupMember($targetMember);
        $this->entityManager->remove($targetMember);

        if ($flush) {
            $this->entityManager->flush();
        }

        $this->staffCircleService->syncUser($targetUser);
    }

    private function assertCanModerateTarget(User $actor, GroupMember $targetMember): void
    {
        if ($targetMember->getUser()->getId() === $actor->getId()) {
            throw new \DomainException('flash.group.self_action');
        }
    }

    private function assertStaffCanActOnTargetRole(User $actor, Group $group, GroupMember $targetMember): void
    {
        if (GroupMemberRole::Owner === $targetMember->getRole()) {
            throw new \DomainException('flash.group.owner_cannot_ban');
        }

        if ($this->groupAccess->isOwner($actor, $group)) {
            return;
        }

        if (GroupMemberRole::Member !== $targetMember->getRole()) {
            throw new \DomainException('flash.group.moderator_ban_member_only');
        }
    }
}
