<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Event;
use App\Entity\Group;
use App\Entity\GroupMember;
use App\Entity\User;
use App\Enum\EventVisibility;
use App\Enum\GroupMemberRole;
use App\Enum\PlatformNoticeVariant;
use App\Repository\GroupMemberRepository;
use App\Repository\GroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class StaffCircleService
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly GroupMemberRepository $groupMemberRepository,
        private readonly MessageService $messageService,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function isStaffCircle(Group $group): bool
    {
        return $group->isStaffCircle();
    }

    public function findStaffCircle(): ?Group
    {
        return $this->groupRepository->findStaffCircle();
    }

    public function ensureStaffCircleExists(): Group
    {
        $circle = $this->findStaffCircle();
        if (null !== $circle) {
            return $circle;
        }

        $circle = (new Group())
            ->setName('Cercle des responsables')
            ->setFamilyName('RapproFam')
            ->setDescription($this->translator->trans('staff_circle.default_description'))
            ->setIsStaffCircle(true);

        $this->entityManager->persist($circle);
        $this->entityManager->flush();

        return $circle;
    }

    /**
     * Synchronise tous les chefs et modérateurs des groupes classiques.
     */
    public function syncAllMembers(): int
    {
        $circle = $this->ensureStaffCircleExists();
        $eligibleUserIds = $this->groupMemberRepository->findAllStaffUserIdsExcludingStaffCircle();
        $currentMemberIds = $this->groupMemberRepository->findUserIdsInGroup($circle);

        $added = 0;
        foreach ($eligibleUserIds as $userId) {
            if (!\in_array($userId, $currentMemberIds, true)) {
                $user = $this->entityManager->getReference(User::class, $userId);
                $this->addUserToCircle($circle, $user, notify: true);
                ++$added;
            }
        }

        foreach ($currentMemberIds as $memberId) {
            if (!\in_array($memberId, $eligibleUserIds, true)) {
                $user = $this->entityManager->getReference(User::class, $memberId);
                $this->removeUserFromCircle($circle, $user, notify: true);
            }
        }

        if ($added > 0 || \count($currentMemberIds) !== \count(array_intersect($currentMemberIds, $eligibleUserIds))) {
            $this->entityManager->flush();
        }

        return $added;
    }

    public function syncUser(User $user): void
    {
        $circle = $this->findStaffCircle();
        if (null === $circle) {
            return;
        }

        $isEligible = $this->isUserEligibleForStaffCircle($user);
        $membership = $this->groupMemberRepository->findOneByUserAndGroup($user, $circle);

        if ($isEligible && null === $membership) {
            $this->addUserToCircle($circle, $user, notify: true);
            $this->entityManager->flush();

            return;
        }

        if (!$isEligible && null !== $membership) {
            $this->removeUserFromCircle($circle, $user, notify: true);
            $this->entityManager->flush();
        }
    }

    public function isUserEligibleForStaffCircle(User $user): bool
    {
        return \in_array($user->getId(), $this->groupMemberRepository->findAllStaffUserIdsExcludingStaffCircle(), true);
    }

    public function canShareEventInStaffCircle(Event $event): bool
    {
        $group = $event->getRelatedGroup();
        if (null === $group || $group->isStaffCircle()) {
            return false;
        }

        return EventVisibility::Public === $event->getVisibility();
    }

    public function applyEventStaffCircleSharing(Event $event, bool $wantsToShare): void
    {
        if ($event->getRelatedGroup()?->isStaffCircle()) {
            $event->setSharedInStaffCircle(false);

            return;
        }

        $event->setSharedInStaffCircle($wantsToShare && EventVisibility::Public === $event->getVisibility());
    }

    private function addUserToCircle(Group $circle, User $user, bool $notify): void
    {
        $membership = (new GroupMember())
            ->setUser($user)
            ->setRole(GroupMemberRole::Member);
        $circle->addGroupMember($membership);
        $this->entityManager->persist($membership);

        if ($notify) {
            $this->messageService->sendPlatformPrivateNotice(
                $user,
                $this->translator->trans('staff_circle.notice.added'),
                PlatformNoticeVariant::RapproFam,
            );
        }
    }

    private function removeUserFromCircle(Group $circle, User $user, bool $notify): void
    {
        $membership = $this->groupMemberRepository->findOneByUserAndGroup($user, $circle);
        if (null === $membership) {
            return;
        }

        $circle->removeGroupMember($membership);
        $this->entityManager->remove($membership);

        if ($notify) {
            $this->messageService->sendPlatformPrivateNotice(
                $user,
                $this->translator->trans('staff_circle.notice.removed'),
                PlatformNoticeVariant::RapproFam,
            );
        }
    }
}
