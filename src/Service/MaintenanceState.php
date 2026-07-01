<?php

declare(strict_types=1);

namespace App\Service;

/**
 * État courant de la maintenance planifiée (Europe/Paris).
 */
final readonly class MaintenanceState
{
    public function __construct(
        public \DateTimeImmutable $start,
        public \DateTimeImmutable $end,
        public int $warnMinutes,
        public bool $active,
        public bool $upcoming,
        public bool $imminent,
        public int $secondsUntilStart,
        public int $secondsUntilEnd,
    ) {
    }

    public function isClientScheduled(): bool
    {
        return $this->upcoming && !$this->active;
    }

    public function showBanner(): bool
    {
        return $this->imminent && !$this->active;
    }

    /**
     * Surveillance JS (polling + redirection) : fenêtre d’avertissement + 2 min de marge.
     */
    public function shouldWatchClient(): bool
    {
        if (!$this->upcoming || $this->active) {
            return false;
        }

        $watchLeadSeconds = max($this->warnMinutes, 1) * 60 + 120;

        return $this->secondsUntilStart <= $watchLeadSeconds;
    }

    public function showEndCountdown(): bool
    {
        return $this->active && $this->secondsUntilEnd > 0;
    }
}
