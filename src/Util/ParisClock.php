<?php

declare(strict_types=1);

namespace App\Util;

/**
 * Horodatage site entier : fuseau Europe/Paris.
 */
final class ParisClock
{
    public const string TIMEZONE = 'Europe/Paris';

    public static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE));
    }
}
