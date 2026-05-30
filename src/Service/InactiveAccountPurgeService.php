<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\GroupRepository;
use App\Repository\UserBanRepository;
use App\Repository\UserRepository;
use App\Util\ParisClock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class InactiveAccountPurgeService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly GroupRepository $groupRepository,
        private readonly UserBanRepository $userBanRepository,
        private readonly SiteStaffService $siteStaff,
        private readonly InactiveAccountNotificationService $notificationService,
        private readonly UserAccountSoftDeleteService $accountSoftDelete,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%ef.inactive.connected.warn1_seconds%')]
        private readonly int $connectedWarn1Seconds,
        #[Autowire('%ef.inactive.connected.warn2_seconds%')]
        private readonly int $connectedWarn2Seconds,
        #[Autowire('%ef.inactive.connected.delete_seconds%')]
        private readonly int $connectedDeleteSeconds,
        #[Autowire('%ef.inactive.unverified.warn_seconds%')]
        private readonly int $unverifiedWarnSeconds,
        #[Autowire('%ef.inactive.unverified.delete_seconds%')]
        private readonly int $unverifiedDeleteSeconds,
    ) {
    }

    /**
     * @return array{warned: int, deleted: int, skipped: int, lines: list<string>}
     */
    public function processEligibleUsers(bool $verbose = false): array
    {
        $stats = ['warned' => 0, 'deleted' => 0, 'skipped' => 0, 'lines' => []];
        $now = ParisClock::now();

        foreach ($this->userRepository->findActiveUsersForInactiveReview() as $user) {
            if ($this->shouldSkipUser($user)) {
                ++$stats['skipped'];
                if ($verbose) {
                    $stats['lines'][] = $this->describeSkip($user);
                }
                continue;
            }

            $inactiveSeconds = $this->getInactiveSeconds($user, $now);
            $action = $this->resolveAction($user, $inactiveSeconds);

            if ('skip' === $action) {
                if ($verbose) {
                    $stats['lines'][] = sprintf(
                        '%s (#%d) — inactif %s, avert. %d → rien à faire (seuils non atteints, ref. %s)',
                        $user->getEmail(),
                        $user->getId() ?? 0,
                        $this->formatDuration($inactiveSeconds),
                        $user->getInactiveWarningCount(),
                        $this->describeInactiveReference($user),
                    );
                }
                continue;
            }

            if ('delete' === $action) {
                $this->purgeUser($user);
                ++$stats['deleted'];
                if ($verbose) {
                    $stats['lines'][] = sprintf(
                        '%s (#%d) — supprimé (inactif %s, avert. %d)',
                        $user->getEmail(),
                        $user->getId() ?? 0,
                        $this->formatDuration($inactiveSeconds),
                        $user->getInactiveWarningCount(),
                    );
                }
                continue;
            }

            $this->sendWarning($user, (int) $action);
            ++$stats['warned'];
            if ($verbose) {
                $stats['lines'][] = sprintf(
                    '%s (#%d) — avertissement %s (inactif %s)',
                    $user->getEmail(),
                    $user->getId() ?? 0,
                    $action,
                    $this->formatDuration($inactiveSeconds),
                );
            }
        }

        return $stats;
    }

    private function describeSkip(User $user): string
    {
        if ($this->siteStaff->isSiteStaff($user)) {
            return sprintf('%s (#%d) — ignoré (staff site)', $user->getEmail(), $user->getId() ?? 0);
        }

        return sprintf('%s (#%d) — ignoré (chef de groupe)', $user->getEmail(), $user->getId() ?? 0);
    }

    private function describeInactiveReference(User $user): string
    {
        if ($user->isVerified()) {
            if (null !== $user->getLastLoginAt()) {
                return 'last_login_at';
            }

            return 'updated_at (pas de last_login_at)';
        }

        return 'created_at';
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 120) {
            return sprintf('%d s', $seconds);
        }

        if ($seconds < 86400) {
            return sprintf('%d min', (int) round($seconds / 60));
        }

        return sprintf('%d j', (int) round($seconds / 86400));
    }

    private function shouldSkipUser(User $user): bool
    {
        if ($this->siteStaff->isSiteStaff($user)) {
            return true;
        }

        return $this->groupRepository->countOwnedByUser($user) > 0;
    }

    private function getInactiveSeconds(User $user, \DateTimeImmutable $now): int
    {
        if ($user->isVerified()) {
            $reference = $user->getLastLoginAt() ?? $user->getUpdatedAt();
        } else {
            $reference = $user->getCreatedAt();
        }

        return max(0, $now->getTimestamp() - $reference->getTimestamp());
    }

    private function resolveAction(User $user, int $inactiveSeconds): string
    {
        $isVerified = $user->isVerified();
        $warningCount = $user->getInactiveWarningCount();

        if ($isVerified) {
            $requiredWarnings = 2;

            if ($inactiveSeconds >= $this->connectedDeleteSeconds && $warningCount >= $requiredWarnings) {
                return 'delete';
            }

            if ($inactiveSeconds >= $this->connectedWarn2Seconds && 1 === $warningCount) {
                return '2';
            }

            if ($inactiveSeconds >= $this->connectedWarn1Seconds && 0 === $warningCount) {
                return '1';
            }

            if ($inactiveSeconds >= $this->connectedDeleteSeconds) {
                return 0 === $warningCount ? '1' : '2';
            }

            return 'skip';
        }

        $requiredWarnings = 1;

        if ($inactiveSeconds >= $this->unverifiedDeleteSeconds && $warningCount >= $requiredWarnings) {
            return 'delete';
        }

        if ($inactiveSeconds >= $this->unverifiedWarnSeconds && 0 === $warningCount) {
            return '1';
        }

        if ($inactiveSeconds >= $this->unverifiedDeleteSeconds) {
            return '1';
        }

        return 'skip';
    }

    private function sendWarning(User $user, int $step): void
    {
        $this->notificationService->sendWarning($user, $step, $user->isVerified());

        $user->setInactiveWarningCount($step);
        $user->setLastInactiveWarningAt(ParisClock::now());
        $this->entityManager->flush();
    }

    private function purgeUser(User $user): void
    {
        $originalEmail = $user->getEmail();
        $wasVerified = $user->isVerified();

        $this->removeFromAllGroups($user);

        $this->accountSoftDelete->softDelete($user);
        $this->notificationService->sendDeletionNotice($originalEmail, $user, $wasVerified);
    }

    private function removeFromAllGroups(User $user): void
    {
        $memberships = $user->getGroupMemberships()->toArray();

        foreach ($memberships as $membership) {
            $group = $membership->getGroup();

            $activeBan = $this->userBanRepository->findActiveBanForUserInGroup($user, $group);
            if (null !== $activeBan) {
                $activeBan->setEndsAt(ParisClock::now());
            }

            $group->removeGroupMember($membership);
            $this->entityManager->remove($membership);
        }
    }
}
