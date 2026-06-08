<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Message;
use App\Repository\MessageRepository;
use App\Util\ParisClock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class MessagePurgeService
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire('%ef.messages.purge_retention_months%')]
        private readonly int $retentionMonths,
    ) {
    }

    /**
     * @return array{deleted: int, lines: list<string>}
     */
    public function purgeOldMessages(bool $verbose = false): array
    {
        $threshold = ParisClock::now()->modify(sprintf('-%d months', max(1, $this->retentionMonths)));
        $roots = $this->messageRepository->findPurgeableRootsOlderThan($threshold);

        $deleted = 0;
        $lines = [];

        foreach ($roots as $root) {
            $lines[] = $this->describeMessageRoot($root);
            $this->entityManager->remove($root);
            ++$deleted;
        }

        if ($deleted > 0) {
            $this->entityManager->flush();
        }

        return ['deleted' => $deleted, 'lines' => $verbose ? $lines : []];
    }

    private function describeMessageRoot(Message $root): string
    {
        if ($root->isGroupMessage()) {
            return sprintf(
                '#%d groupe « %s »',
                $root->getId(),
                $root->getRelatedGroup()?->getName() ?? '?',
            );
        }

        return sprintf('#%d MP', $root->getId());
    }
}
