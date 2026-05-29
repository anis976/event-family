<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Statut d'une demande pour rejoindre un groupe (ou invitation chef → membre).
 */
enum GroupRequestStatus: string
{
    case Pending = 'PENDING';
    case Accepted = 'ACCEPTED';
    case Refused = 'REFUSED';
    case Invited = 'INVITED';
}
