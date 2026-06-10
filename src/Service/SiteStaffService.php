<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\PlatformNoticeVariant;

final class SiteStaffService
{
    public function isSiteStaff(User $user): bool
    {
        $roles = $user->getRoles();

        return \in_array(User::ROLE_ADMIN, $roles, true)
            || \in_array(User::ROLE_SUPER_MODERATOR, $roles, true)
            || \in_array(User::ROLE_MODERATOR, $roles, true);
    }

    public function isSiteAdmin(User $user): bool
    {
        return \in_array(User::ROLE_ADMIN, $user->getRoles(), true);
    }

    /**
     * @return list<string> Valeurs send_mode autorisées pour un MP staff (hors « member »).
     */
    public function getAllowedStaffSendModes(User $user): array
    {
        $modes = ['staff_moderator'];

        if ($this->isSiteAdmin($user)) {
            $modes[] = 'staff_admin';
        }

        return $modes;
    }

    public function resolvePrivateNoticeVariant(User $sender, string $sendMode): PlatformNoticeVariant
    {
        if ('staff_admin' === $sendMode) {
            if (!$this->isSiteAdmin($sender)) {
                throw new \DomainException('flash.message.staff_admin_only');
            }

            return PlatformNoticeVariant::RapporFam;
        }

        if ('staff_moderator' === $sendMode || 'staff' === $sendMode) {
            if (!$this->isSiteStaff($sender)) {
                throw new \DomainException('flash.message.access_denied');
            }

            return PlatformNoticeVariant::Moderator;
        }

        throw new \DomainException('flash.message.access_denied');
    }
}
