<?php

declare(strict_types=1);

namespace App\Service;

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

            return;
        }

        if ($this->groupMemberRepository->countModeratorsInGroup($group) >= 1) {
            throw new \DomainException('flash.group.single_moderator');
        }

        $targetMember->setRole(GroupMemberRole::Moderator);
        $this->entityManager->flush();
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

        $activeBan = $this->userBanRepository->findActiveBanForUserInGroup($targetMember->getUser(), $group);
        if (null !== $activeBan) {
            $activeBan->setEndsAt(ParisClock::now());
        }

        $group->removeGroupMember($targetMember);
        $this->entityManager->remove($targetMember);
        $this->entityManager->flush();
    }

    private function assertCanModerateTarget(User $actor, GroupMember $targetMember): void
    {
        if ($targetMember->getUser()->getId() === $actor->getId()) {
            throw new \DomainException('flash.group.self_action');
        }
    }

    private function assertStaffCanActOnTargetRole(User $actor, \App\Entity\Group $group, GroupMember $targetMember): void
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
