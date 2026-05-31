<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\EventPurgeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:events:purge-past',
    description: 'Supprime définitivement les événements passés au-delà de la durée de rétention.',
)]
final class PurgePastEventsCommand extends Command
{
    public function __construct(
        private readonly EventPurgeService $purgeService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $stats = $this->purgeService->purgeExpiredPastEvents($output->isVerbose());

        if ($output->isVerbose() && [] !== $stats['lines']) {
            $io->section('Événements supprimés');
            $io->listing($stats['lines']);
        }

        if (0 === $stats['deleted']) {
            $io->success('Aucun événement passé à purger.');
        } else {
            $io->success(sprintf('%d événement(s) passé(s) supprimé(s).', $stats['deleted']));
        }

        return Command::SUCCESS;
    }
}
