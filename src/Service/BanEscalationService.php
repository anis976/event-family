<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Group;
use App\Entity\User;
use App\Entity\UserBan;
use App\Enum\PlatformNoticeVariant;
use App\Repository\UserBanRepository;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

final class BanEscalationService
{
    public const MAX_BANS_BEFORE_DELETION = 3;

    public function __construct(
        private readonly UserBanRepository $userBanRepository,
        private readonly BanNotificationService $banNotification,
        private readonly MessageService $messageService,
        private readonly UserAccountSoftDeleteService $accountSoftDelete,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function handleAfterGroupBan(UserBan $ban): void
    {
        try {
            $this->processBanEscalation($ban);
        } catch (TransportExceptionInterface) {
            // Le bannissement groupe reste effectif même si l'e-mail échoue.
        }
    }

    private function processBanEscalation(UserBan $ban): void
    {
        $user = $ban->getBannedUser();
        $group = $ban->getRelatedGroup();
        $reason = trim($ban->getReason());
        $banCount = $this->userBanRepository->countTotalBansForUser($user);

        if ($banCount >= self::MAX_BANS_BEFORE_DELETION) {
            $this->handleThirdBan($user, $group, $reason, $banCount);

            return;
        }

        if (2 === $banCount) {
            $this->notifyWarning($user, $group, $reason, 2, PlatformNoticeVariant::EventFamily);

            return;
        }

        $this->notifyWarning($user, $group, $reason, 1, PlatformNoticeVariant::EventFamily);
    }

    /**
     * @throws TransportExceptionInterface
     */
    private function handleThirdBan(User $user, ?Group $group, string $reason, int $banCount): void
    {
        $privateContent = $this->banNotification->buildPrivateNoticeContent(3, $reason, $group, $user);
        $this->messageService->sendPlatformPrivateNotice($user, $privateContent, PlatformNoticeVariant::System);

        $userLocale = $user->getLocale();
        $originalEmail = $this->accountSoftDelete->softDelete($user);
        $this->banNotification->sendAccountDeletedEmail($originalEmail, $reason, $group, $banCount, $userLocale);
    }

    /**
     * @throws TransportExceptionInterface
     */
    private function notifyWarning(
        User $user,
        ?Group $group,
        string $reason,
        int $step,
        PlatformNoticeVariant $variant,
    ): void {
        $privateContent = $this->banNotification->buildPrivateNoticeContent($step, $reason, $group, $user);
        $this->messageService->sendPlatformPrivateNotice($user, $privateContent, $variant);
        $this->banNotification->sendWarningEmail($user, $step, $reason, $group);
    }
}
