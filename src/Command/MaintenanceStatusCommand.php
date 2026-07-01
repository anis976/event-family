<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\MaintenanceScheduleService;
use App\Util\ParisClock;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ef:maintenance:status',
    description: 'Affiche l’état courant de la maintenance planifiée (debug).',
)]
final class MaintenanceStatusCommand extends Command
{
    public function __construct(
        private readonly MaintenanceScheduleService $maintenance,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = ParisClock::now();

        $io->title('Maintenance planifiée');
        $io->text('Heure serveur (Paris) : '.$now->format('Y-m-d H:i:s'));

        if (!$this->maintenance->isConfigured()) {
            $io->warning('Non configurée (dates vides ou fin ≤ début).');

            return Command::SUCCESS;
        }

        $state = $this->maintenance->getState();
        if (null === $state) {
            $io->error('État indisponible.');

            return Command::FAILURE;
        }

        $io->table(
            ['Clé', 'Valeur'],
            [
                ['Début', $state->start->format('Y-m-d H:i')],
                ['Fin', $state->end->format('Y-m-d H:i')],
                ['WARN (min)', (string) $state->warnMinutes],
                ['Secondes avant début', (string) $state->secondsUntilStart],
                ['Active', $state->active ? 'oui' : 'non'],
                ['À venir', $state->upcoming ? 'oui' : 'non'],
                ['Imminente', $state->imminent ? 'oui' : 'non'],
                ['Planifiée (client)', $state->isClientScheduled() ? 'oui' : 'non'],
                ['→ Bandeau', $state->showBanner() ? 'OUI' : 'non'],
                ['→ Surveillance JS', $state->shouldWatchClient() ? 'OUI' : 'non'],
            ],
        );

        return Command::SUCCESS;
    }
}
