<?php

declare(strict_types=1);

namespace App\Enum;

enum EventKind: string
{
    case Wedding = 'wedding';
    case Birthday = 'birthday';
    case Party = 'party';
    case Gathering = 'gathering';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Wedding => 'Mariage',
            self::Birthday => 'Anniversaire',
            self::Party => 'Fête',
            self::Gathering => 'Réunion de famille',
            self::Other => 'Autre',
        };
    }
}
