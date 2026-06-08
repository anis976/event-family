<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RecaptchaVerifierService
{
    private const string VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    private const string EXPECTED_ACTION = 'contact';

    /** @var list<string> */
    private array $lastErrorCodes = [];

    private bool $lastTokenEmpty = false;

    /** @var array<string, mixed> */
    private array $lastPayload = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(RECAPTCHA_SECRET_KEY)%')]
        private readonly string $secretKey,
        #[Autowire('%ef.contact.recaptcha_min_score%')]
        private readonly float $minScore,
    ) {
    }

    public function isEnabled(): bool
    {
        return '' !== trim($this->secretKey);
    }

    public function wasLastTokenEmpty(): bool
    {
        return $this->lastTokenEmpty;
    }

    /**
     * @return list<string>
     */
    public function getLastErrorCodes(): array
    {
        return $this->lastErrorCodes;
    }

    public function hasHostnameMismatch(): bool
    {
        return \in_array('hostname-mismatch', $this->lastErrorCodes, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function getLastPayload(): array
    {
        return $this->lastPayload;
    }

    public function getLastDebugSummary(): string
    {
        if ($this->lastTokenEmpty) {
            return 'token vide (JS non exécuté ou champ absent du POST)';
        }

        if ([] === $this->lastPayload) {
            return implode(', ', $this->lastErrorCodes) ?: 'échec inconnu';
        }

        $parts = [];
        if (isset($this->lastPayload['success'])) {
            $parts[] = 'success='.(true === $this->lastPayload['success'] ? '1' : '0');
        }
        if (isset($this->lastPayload['score'])) {
            $parts[] = 'score='.$this->lastPayload['score'];
        }
        if (isset($this->lastPayload['action'])) {
            $parts[] = 'action='.$this->lastPayload['action'];
        }
        if (isset($this->lastPayload['hostname'])) {
            $parts[] = 'hostname='.$this->lastPayload['hostname'];
        }
        if ([] !== $this->lastErrorCodes) {
            $parts[] = 'codes='.implode(',', $this->lastErrorCodes);
        }

        return implode(' ; ', $parts) ?: 'réponse Google vide';
    }

    public function verify(string $token, ?string $remoteIp): bool
    {
        $this->lastErrorCodes = [];
        $this->lastTokenEmpty = false;
        $this->lastPayload = [];

        if (!$this->isEnabled()) {
            return true;
        }

        $token = trim($token);
        if ('' === $token) {
            $this->lastTokenEmpty = true;

            return false;
        }

        try {
            $body = [
                'secret' => $this->secretKey,
                'response' => $token,
            ];
            if (null !== $remoteIp && '' !== $remoteIp) {
                $body['remoteip'] = $remoteIp;
            }

            $response = $this->httpClient->request('POST', self::VERIFY_URL, [
                'body' => $body,
            ]);

            $payload = $response->toArray(false);
            $this->lastPayload = $payload;

            if (!($payload['success'] ?? false)) {
                $codes = $payload['error-codes'] ?? [];
                $this->lastErrorCodes = \is_array($codes)
                    ? array_values(array_filter($codes, \is_string(...)))
                    : [];

                return false;
            }

            $action = $payload['action'] ?? null;
            if (\is_string($action) && '' !== $action && self::EXPECTED_ACTION !== $action) {
                $this->lastErrorCodes = ['action-mismatch'];

                return false;
            }

            if (!\array_key_exists('score', $payload)) {
                return true;
            }

            $score = (float) $payload['score'];
            if ($score < $this->minScore) {
                $this->lastErrorCodes = ['low-score'];

                return false;
            }

            return true;
        } catch (\Throwable) {
            $this->lastErrorCodes = ['request-failed'];

            return false;
        }
    }
}
