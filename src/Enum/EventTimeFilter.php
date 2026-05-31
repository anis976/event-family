<?php

declare(strict_types=1);

namespace App\Enum;

enum EventTimeFilter: string
{
    case Upcoming = 'upcoming';
    case Ongoing = 'ongoing';
    case Past = 'past';

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'À venir',
            self::Ongoing => 'En cours',
            self::Past => 'Passés',
        };
    }

    public function emptyMessage(): string
    {
        return match ($this) {
            self::Upcoming => 'Aucun événement à venir pour le moment.',
            self::Ongoing => 'Aucun événement en cours pour le moment.',
            self::Past => 'Aucun événement passé pour le moment.',
        };
    }

    public static function fromRequest(?string $value): self
    {
        return match ($value) {
            self::Ongoing->value => self::Ongoing,
            self::Past->value => self::Past,
            default => self::Upcoming,
        };
    }
}
