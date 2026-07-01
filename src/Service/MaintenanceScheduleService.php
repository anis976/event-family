<?php

declare(strict_types=1);

namespace App\Service;

use App\Util\ParisClock;

/**
 * Fenêtre de maintenance planifiée (variables EF_MAINTENANCE_*).
 * Fuseau : Europe/Paris.
 */
final class MaintenanceScheduleService
{
    private const string DATETIME_PATTERN = 'Y-m-d H:i';

    public function __construct(
        private readonly string $startRaw,
        private readonly string $endRaw,
        private readonly int $warnMinutes,
    ) {
    }

    public function isConfigured(): bool
    {
        return null !== $this->resolveWindow();
    }

    public function isActive(): bool
    {
        return $this->getState()?->active ?? false;
    }

    public function isSiteEffectivelyClosed(bool $manualClosed): bool
    {
        return $manualClosed || $this->isActive();
    }

    public function getState(): ?MaintenanceState
    {
        $window = $this->resolveWindow();

        if (null === $window) {
            return null;
        }

        [$start, $end] = $window;
        $now = ParisClock::now();
        $nowTs = $now->getTimestamp();
        $startTs = $start->getTimestamp();
        $endTs = $end->getTimestamp();

        $active = $nowTs >= $startTs && $nowTs < $endTs;
        $upcoming = $nowTs < $startTs;
        $warnSeconds = max(0, $this->warnMinutes) * 60;
        $imminent = $upcoming && $warnSeconds > 0 && $nowTs >= ($startTs - $warnSeconds);

        return new MaintenanceState(
            start: $start,
            end: $end,
            warnMinutes: max(0, $this->warnMinutes),
            active: $active,
            upcoming: $upcoming,
            imminent: $imminent,
            secondsUntilStart: max(0, $startTs - $nowTs),
            secondsUntilEnd: max(0, $endTs - $nowTs),
        );
    }

    public function getEnd(): ?\DateTimeImmutable
    {
        return $this->resolveWindow()[1] ?? null;
    }

    /**
     * @return array{\DateTimeImmutable, \DateTimeImmutable}|null
     */
    private function resolveWindow(): ?array
    {
        $start = $this->parseDateTime($this->startRaw);
        $end = $this->parseDateTime($this->endRaw);

        if (null === $start || null === $end || $start >= $end) {
            return null;
        }

        return [$start, $end];
    }

    private function parseDateTime(string $raw): ?\DateTimeImmutable
    {
        $raw = trim($raw);

        if ('' === $raw) {
            return null;
        }

        $timezone = new \DateTimeZone(ParisClock::TIMEZONE);

        $parsed = \DateTimeImmutable::createFromFormat(self::DATETIME_PATTERN, $raw, $timezone);
        if ($parsed instanceof \DateTimeImmutable) {
            return $parsed;
        }

        try {
            return new \DateTimeImmutable($raw, $timezone);
        } catch (\Exception) {
            return null;
        }
    }
}
