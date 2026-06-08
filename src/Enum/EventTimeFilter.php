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
            self::Upcoming => 'ui.events.filter.upcoming',
            self::Ongoing => 'ui.events.filter.ongoing',
            self::Past => 'ui.events.filter.past',
        };
    }

    public function emptyMessage(): string
    {
        return match ($this) {
            self::Upcoming => 'ui.events.empty.upcoming',
            self::Ongoing => 'ui.events.empty.ongoing',
            self::Past => 'ui.events.empty.past',
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
