<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'ef:google-oauth:diagnose',
    description: 'Affiche l’URI de redirection OAuth générée (à comparer avec Google Cloud Console).',
)]
final class GoogleOAuthDiagnoseCommand extends Command
{
    public function __construct(
        #[Autowire('%env(ef_google_oauth_redirect:GOOGLE_OAUTH_REDIRECT_URI)%')]
        private readonly string $redirectUri,
        #[Autowire('%env(DEFAULT_URI)%')]
        private readonly string $defaultUri,
        #[Autowire('%env(GOOGLE_OAUTH_CLIENT_ID)%')]
        private readonly string $clientId,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'URL actuelle dans le navigateur (pour vérifier l’alignement hôte)', 'http://localhost:8000/login');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $simulatedUrl = (string) $input->getOption('url');
        $browserHost = parse_url($simulatedUrl, PHP_URL_HOST) ?: '?';
        $canonicalHost = parse_url($this->defaultUri, PHP_URL_HOST) ?: '?';

        $clientSuffix = '' !== $this->clientId
            ? substr($this->clientId, -20)
            : '(vide)';

        $io->title('Diagnostic Google OAuth');
        $io->definitionList(
            ['DEFAULT_URI' => $this->defaultUri ?: '(vide)'],
            ['Hôte navigateur (option --url)' => $browserHost],
            ['Hôte canonique (DEFAULT_URI)' => $canonicalHost],
            ['GOOGLE_OAUTH_CLIENT_ID (fin)' => '…'.$clientSuffix],
            ['redirect_uri envoyée à Google' => $this->redirectUri],
        );

        if ($browserHost !== $canonicalHost) {
            $io->warning(sprintf(
                'Le navigateur utilise « %s » mais OAuth envoie « %s ». En dev, tu seras redirigé vers %s.',
                $browserHost,
                $canonicalHost,
                $this->defaultUri,
            ));
        }

        $io->section('À enregistrer dans Google Cloud (URI de redirection autorisés)');
        $io->listing([$this->redirectUri]);

        $io->note([
            'L’URI doit correspondre caractère par caractère (schéma, hôte, port, chemin).',
            'Clique « Enregistrer » dans Google Cloud après toute modification.',
            'Vérifie que le CLIENT_ID dans .env.local correspond à cet écran Google.',
        ]);

        return Command::SUCCESS;
    }
}
