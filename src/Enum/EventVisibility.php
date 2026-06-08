<?php

declare(strict_types=1);

namespace App\Enum;

enum EventVisibility: string
{
    case Public = 'public';
    case Group = 'group';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'event.visibility.public',
            self::Group => 'event.visibility.group',
        };
    }
}
