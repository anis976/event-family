<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Badges invitations / messages : chargés en AJAX (voir ef-notifications.js)
 * pour éviter plusieurs requêtes SQL sur chaque page HTML.
 */
final class NotificationExtension extends AbstractExtension implements GlobalsInterface
{
    public function getGlobals(): array
    {
        return [
            'ef_invitation_count' => 0,
            'ef_message_count' => 0,
            'ef_notification_count' => 0,
            'ef_notification_target_route' => 'app_messages',
        ];
    }
}
