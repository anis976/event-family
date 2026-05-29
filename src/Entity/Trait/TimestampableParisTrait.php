<?php

declare(strict_types=1);

namespace App\Entity\Trait;

use App\Util\ParisClock;
use Doctrine\ORM\Mapping as ORM;

/**
 * Initialise createdAt / updatedAt à Paris sur persist & update.
 * L'entité doit déclarer #[ORM\HasLifecycleCallbacks] et les propriétés $createdAt / $updatedAt.
 */
trait TimestampableParisTrait
{
    #[ORM\PrePersist]
    public function onPrePersistTimestamps(): void
    {
        $now = ParisClock::now();

        if (!isset($this->createdAt) || null === $this->createdAt) {
            $this->createdAt = $now;
        }

        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdateTimestamps(): void
    {
        $this->updatedAt = ParisClock::now();
    }
}
