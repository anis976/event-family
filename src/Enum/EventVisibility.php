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
            self::Public => 'Public (tous les utilisateurs du site)',
            self::Group => 'Privé (membres du groupe uniquement)',
        };
    }
}
