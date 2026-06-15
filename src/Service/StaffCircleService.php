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
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class StaffCircleService
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly GroupMemberRepository $groupMemberRepository,
        private readonly MessageService $messageService,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function transCommandMessage(string $id, array $parameters = []): string
    {
        return $this->translator->trans($id, $parameters);
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

        $legacyName = $this->translator->trans('ui.groups.staff_circle.group_name', locale: 'fr');
        $legacy = $this->groupRepository->findOneBy(['name' => $legacyName]);
        if (null !== $legacy) {
            if (!$legacy->isStaffCircle()) {
                $legacy->setIsStaffCircle(true);
                $this->entityManager->flush();
            }

            return $legacy;
        }

        $circle = (new Group())
            ->setName($this->translator->trans('ui.groups.staff_circle.group_name', locale: 'fr'))
            ->setFamilyName($this->translator->trans('ui.groups.staff_circle.group_family', locale: 'fr'))
            ->setDescription($this->translator->trans('staff_circle.default_description', locale: 'fr'))
            ->setIsStaffCircle(true);

        $this->entityManager->persist($circle);
        $this->entityManager->flush();

        return $circle;
    }

    /**
     * @return array{added: int, removed: int, eligible: int, current: int}
     */
    public function syncAllMembers(bool $notify = true): array
    {
        $circle = $this->ensureStaffCircleExists();
        $eligibleUserIds = $this->groupMemberRepository->findAllStaffUserIdsExcludingStaffCircle();
        $currentMemberIds = $this->groupMemberRepository->findUserIdsInGroup($circle);

        $addedUsers = [];
        foreach ($eligibleUserIds as $userId) {
            if (!\in_array($userId, $currentMemberIds, true)) {
                $user = $this->entityManager->find(User::class, $userId);
                if (null === $user || null !== $user->getDeletedAt()) {
                    continue;
                }
                $this->persistMembership($circle, $user);
                $addedUsers[] = $user;
            }
        }

        $removedUsers = [];
        foreach ($currentMemberIds as $memberId) {
            if (!\in_array($memberId, $eligibleUserIds, true)) {
                $user = $this->entityManager->find(User::class, $memberId);
                if (null === $user) {
                    continue;
                }
                $this->removeMembership($circle, $user);
                $removedUsers[] = $user;
            }
        }

        if ([] !== $addedUsers || [] !== $removedUsers) {
            $this->entityManager->flush();
        }

        if ($notify) {
            foreach ($addedUsers as $user) {
                $this->safeNotifyAdded($user);
            }
            foreach ($removedUsers as $user) {
                $this->safeNotifyRemoved($user);
            }
        }

        return [
            'added' => \count($addedUsers),
            'removed' => \count($removedUsers),
            'eligible' => \count($eligibleUserIds),
            'current' => \count($this->groupMemberRepository->findUserIdsInGroup($circle)),
        ];
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
            $this->persistMembership($circle, $user);
            $this->entityManager->flush();
            $this->safeNotifyAdded($user);

            return;
        }

        if (!$isEligible && null !== $membership) {
            $this->removeMembership($circle, $user);
            $this->entityManager->flush();
            $this->safeNotifyRemoved($user);
        }
    }

    public function isUserEligibleForStaffCircle(User $user): bool
    {
        if (null === $user->getId() || null !== $user->getDeletedAt()) {
            return false;
        }

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

    private function persistMembership(Group $circle, User $user): void
    {
        $membership = (new GroupMember())
            ->setUser($user)
            ->setRole(GroupMemberRole::Member);
        $circle->addGroupMember($membership);
        $this->entityManager->persist($membership);
    }

    private function removeMembership(Group $circle, User $user): void
    {
        $membership = $this->groupMemberRepository->findOneByUserAndGroup($user, $circle);
        if (null === $membership) {
            return;
        }

        $circle->removeGroupMember($membership);
        $this->entityManager->remove($membership);
    }

    private function safeNotifyAdded(User $user): void
    {
        try {
            $this->messageService->sendPlatformPrivateNotice(
                $user,
                $this->translator->trans('staff_circle.notice.added', locale: $user->getLocale()),
                PlatformNoticeVariant::RapproFam,
            );
        } catch (\Throwable $exception) {
            $this->logger->warning('Notification ajout cercle responsables non envoyée.', [
                'user_id' => $user->getId(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function safeNotifyRemoved(User $user): void
    {
        try {
            $this->messageService->sendPlatformPrivateNotice(
                $user,
                $this->translator->trans('staff_circle.notice.removed', locale: $user->getLocale()),
                PlatformNoticeVariant::RapproFam,
            );
        } catch (\Throwable $exception) {
            $this->logger->warning('Notification retrait cercle responsables non envoyée.', [
                'user_id' => $user->getId(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
