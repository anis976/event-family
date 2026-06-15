<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\StaffCircleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ef:staff-circle:sync',
    description: 'Synchronise les membres du cercle des responsables (chefs et modérateurs).',
)]
final class SyncStaffCircleCommand extends Command
{
    public function __construct(
        private readonly StaffCircleService $staffCircleService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $added = $this->staffCircleService->syncAllMembers();
        $io->success(sprintf('Synchronisation terminée — %d nouveau(x) membre(s) ajouté(s).', $added));

        return Command::SUCCESS;
    }
}
