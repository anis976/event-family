<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Util\ParisClock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class EventPurgeService
{
    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly EventImageService $eventImageService,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%ef.events.purge_retention_months%')]
        private readonly int $retentionMonths,
    ) {
    }

    /**
     * @return array{deleted: int, lines: list<string>}
     */
    public function purgeExpiredPastEvents(bool $verbose = false): array
    {
        $threshold = ParisClock::now()->modify(sprintf('-%d months', max(1, $this->retentionMonths)));
        $events = $this->eventRepository->findPastOlderThan($threshold);

        $deleted = 0;
        $lines = [];

        foreach ($events as $event) {
            $lines[] = sprintf('#%d « %s »', $event->getId(), $event->getTitle());
            $this->eventImageService->deleteEventFiles($event);
            $event->setPhotoCover(null);
            $event->setPhotoDetail(null);
            $this->entityManager->remove($event);
            ++$deleted;
        }

        if ($deleted > 0) {
            $this->entityManager->flush();
        }

        return ['deleted' => $deleted, 'lines' => $verbose ? $lines : []];
    }
}
