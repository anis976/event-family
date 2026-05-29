<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Service\NotificationCountService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class NotificationExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly NotificationCountService $notificationCountService,
        private readonly Security $security,
    ) {
    }

    public function getGlobals(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [
                'ef_invitation_count' => 0,
                'ef_message_count' => 0,
                'ef_notification_count' => 0,
                'ef_is_staff_anywhere' => false,
                'ef_notification_target_route' => 'app_messages',
            ];
        }

        $counts = $this->notificationCountService->getCounts($user);

        return [
            'ef_invitation_count' => $counts['invitations'],
            'ef_message_count' => $counts['messages'],
            'ef_notification_count' => $counts['total'],
            'ef_is_staff_anywhere' => $this->notificationCountService->isStaffAnywhere($user),
            'ef_notification_target_route' => $this->notificationCountService->resolveBellTargetRoute($user),
        ];
    }
}
