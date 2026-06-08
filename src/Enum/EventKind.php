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
            self::Wedding => 'event.kind.wedding',
            self::Birthday => 'event.kind.birthday',
            self::Party => 'event.kind.party',
            self::Gathering => 'event.kind.gathering',
            self::Other => 'event.kind.other',
        };
    }
}
