<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\RecaptchaVerifierService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:recaptcha:diagnose',
    description: 'Teste la connectivité et la clé secrète reCAPTCHA (dev)',
)]
final class RecaptchaDiagnoseCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly RecaptchaVerifierService $recaptcha,
        #[Autowire('%env(RECAPTCHA_SITE_KEY)%')]
        private readonly string $siteKey,
        #[Autowire('%env(RECAPTCHA_SECRET_KEY)%')]
        private readonly string $secretKey,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('reCAPTCHA diagnose');

        $siteKey = trim($this->siteKey);
        $secretKey = trim($this->secretKey);

        $io->table(
            ['Paramètre', 'Valeur'],
            [
                ['RECAPTCHA_SITE_KEY', '' === $siteKey ? '(vide)' : substr($siteKey, 0, 8).'…'],
                ['RECAPTCHA_SECRET_KEY', '' === $secretKey ? '(vide)' : substr($secretKey, 0, 8).'…'],
                ['Service actif', $this->recaptcha->isEnabled() ? 'oui' : 'non'],
            ],
        );

        if ('' === $secretKey) {
            $io->warning('Clé secrète vide — reCAPTCHA désactivé côté serveur.');

            return Command::SUCCESS;
        }

        try {
            $response = $this->httpClient->request('POST', 'https://www.google.com/recaptcha/api/siteverify', [
                'body' => [
                    'secret' => $secretKey,
                    'response' => 'diagnostic-token',
                ],
            ]);
            $payload = $response->toArray(false);
        } catch (\Throwable $e) {
            $io->error('Requête HTTP vers Google impossible : '.$e->getMessage());

            return Command::FAILURE;
        }

        $io->section('Réponse Google (token factice)');
        $io->writeln(json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));

        $codes = $payload['error-codes'] ?? [];
        if (\is_array($codes) && \in_array('invalid-input-secret', $codes, true)) {
            $io->error('Clé secrète invalide — vérifie RECAPTCHA_SECRET_KEY ou régénère les clés (type v3).');
        } elseif (\is_array($codes) && \in_array('invalid-input-response', $codes, true)) {
            $io->success('Clé secrète acceptée par Google (invalid-input-response = normal avec un faux token).');
        }

        $io->note([
            'Les domaines Google sont des noms d’hôte seuls : localhost et 127.0.0.1 (pas d’URL ni de /contact).',
            'Le formulaire contact utilise reCAPTCHA v3 (invisible) : pas de case à cocher.',
            'Si invalid-input-secret : mauvaise clé ou clés v2 incompatibles avec notre intégration v3.',
        ]);

        return Command::SUCCESS;
    }
}
