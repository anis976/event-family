<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;

final class SiteStaffService
{
    public function isSiteStaff(User $user): bool
    {
        $roles = $user->getRoles();

        return \in_array(User::ROLE_ADMIN, $roles, true)
            || \in_array(User::ROLE_MODERATOR, $roles, true);
    }
}
