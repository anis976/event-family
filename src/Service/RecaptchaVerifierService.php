<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RecaptchaVerifierService
{
    private const string VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    private const float MIN_SCORE = 0.5;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(RECAPTCHA_SECRET_KEY)%')]
        private readonly string $secretKey,
    ) {
    }

    public function isEnabled(): bool
    {
        return '' !== trim($this->secretKey);
    }

    public function verify(string $token, ?string $remoteIp): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        $token = trim($token);
        if ('' === $token) {
            return false;
        }

        try {
            $response = $this->httpClient->request('POST', self::VERIFY_URL, [
                'body' => [
                    'secret' => $this->secretKey,
                    'response' => $token,
                    'remoteip' => $remoteIp ?? '',
                ],
            ]);

            $payload = $response->toArray(false);

            if (!($payload['success'] ?? false)) {
                return false;
            }

            $score = (float) ($payload['score'] ?? 0.0);

            return $score >= self::MIN_SCORE;
        } catch (\Throwable) {
            return false;
        }
    }
}
