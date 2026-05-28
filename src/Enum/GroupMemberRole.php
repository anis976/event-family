<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Rôle d'un utilisateur au sein d'un groupe (distinct des rôles site Symfony).
 */
enum GroupMemberRole: string
{
    case Owner = 'OWNER';
    case Moderator = 'MODERATOR';
    case Member = 'MEMBER';
}
