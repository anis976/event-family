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
        $result = $this->staffCircleService->syncAllMembers();

        $io->table(
            ['Éligibles', 'Membres actuels', 'Ajoutés', 'Retirés'],
            [[
                (string) $result['eligible'],
                (string) $result['current'],
                (string) $result['added'],
                (string) $result['removed'],
            ]],
        );

        if (0 === $result['eligible']) {
            $io->warning('Aucun chef ou modérateur trouvé dans les groupes classiques.');
        } elseif (0 === $result['added'] && 0 === $result['removed']) {
            $io->success('Synchronisation terminée — déjà à jour.');
        } else {
            $io->success(sprintf(
                'Synchronisation terminée — %d ajouté(s), %d retiré(s).',
                $result['added'],
                $result['removed'],
            ));
        }

        return Command::SUCCESS;
    }
}
