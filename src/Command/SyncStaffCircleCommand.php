<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\StaffCircleService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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

    protected function configure(): void
    {
        $this->addOption(
            'no-notify',
            null,
            InputOption::VALUE_NONE,
            'Ne pas envoyer de messages privés aux membres ajoutés ou retirés.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $notify = !$input->getOption('no-notify');
        $result = $this->staffCircleService->syncAllMembers($notify);

        $io->table(
            [
                $this->trans('command.staff_circle_sync.table_eligible'),
                $this->trans('command.staff_circle_sync.table_current'),
                $this->trans('command.staff_circle_sync.table_added'),
                $this->trans('command.staff_circle_sync.table_removed'),
            ],
            [[
                (string) $result['eligible'],
                (string) $result['current'],
                (string) $result['added'],
                (string) $result['removed'],
            ]],
        );

        if (0 === $result['eligible']) {
            $io->warning($this->trans('command.staff_circle_sync.warning_no_staff'));
        } elseif (0 === $result['added'] && 0 === $result['removed']) {
            $io->success($this->trans('command.staff_circle_sync.success_up_to_date'));
        } else {
            $io->success($this->trans('command.staff_circle_sync.success_done', [
                '%added%' => (string) $result['added'],
                '%removed%' => (string) $result['removed'],
            ]));
        }

        return Command::SUCCESS;
    }

    private function trans(string $id, array $parameters = []): string
    {
        return $this->staffCircleService->transCommandMessage($id, $parameters);
    }
}
