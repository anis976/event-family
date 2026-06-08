<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\MessagePurgeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:messages:purge-old',
    description: 'Supprime définitivement les messages MP et de groupe au-delà de la durée de rétention.',
)]
final class PurgeOldMessagesCommand extends Command
{
    public function __construct(
        private readonly MessagePurgeService $purgeService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $stats = $this->purgeService->purgeOldMessages($output->isVerbose());

        if ($output->isVerbose() && [] !== $stats['lines']) {
            $io->section('Messages supprimés');
            $io->listing($stats['lines']);
        }

        if (0 === $stats['deleted']) {
            $io->success('Aucun message à purger.');
        } else {
            $io->success(sprintf('%d fil(s) de discussion supprimé(s) définitivement.', $stats['deleted']));
        }

        return Command::SUCCESS;
    }
}
