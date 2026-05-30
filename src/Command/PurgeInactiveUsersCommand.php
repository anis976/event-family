<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\InactiveAccountPurgeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:users:purge-inactive',
    description: 'Avertit puis supprime (soft-delete) les comptes inactifs.',
)]
final class PurgeInactiveUsersCommand extends Command
{
    public function __construct(
        private readonly InactiveAccountPurgeService $purgeService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $stats = $this->purgeService->processEligibleUsers($output->isVerbose());

        if ($output->isVerbose() && [] !== $stats['lines']) {
            $io->section('Détail');
            $io->listing($stats['lines']);
        }

        if (0 === $stats['warned'] && 0 === $stats['deleted']) {
            $io->note([
                'La purge ne se déclenche pas toute seule après une modification en BDD.',
                'Ordre : 1) modifier last_login_at (compte vérifié) → 2) lancer cette commande → 3) se connecter pour lire le message.',
                'Si vous vous connectez avec le compte test AVANT la commande, last_login_at est remis à maintenant et les avertissements sont remis à zéro.',
                'Vérifiez aussi que vous modifiez la ligne du compte actif (deleted_at vide), pas un compte déjà supprimé.',
                'Utilisez -v pour voir le détail par compte.',
            ]);
        }

        $io->success(sprintf(
            'Terminé — avertissements : %d, suppressions : %d, ignorés (staff/chefs) : %d',
            $stats['warned'],
            $stats['deleted'],
            $stats['skipped'],
        ));

        return Command::SUCCESS;
    }
}
