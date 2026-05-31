<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class ContactSpamGuardService
{
    private const int MIN_SUBMIT_DELAY_SECONDS = 3;

    public function __construct(
        private readonly RecaptchaVerifierService $recaptcha,
        #[Autowire(service: 'limiter.contact_form_hourly')]
        private readonly RateLimiterFactory $hourlyLimiter,
        #[Autowire(service: 'limiter.contact_form_daily')]
        private readonly RateLimiterFactory $dailyLimiter,
    ) {
    }

    public function isRecaptchaEnabled(): bool
    {
        return $this->recaptcha->isEnabled();
    }

    public function isHoneypotTriggered(?string $honeypotValue): bool
    {
        return null !== $honeypotValue && '' !== trim($honeypotValue);
    }

    public function isSubmittedTooFast(?string $startedAt): bool
    {
        $started = (int) $startedAt;

        if ($started <= 0) {
            return true;
        }

        return (time() - $started) < self::MIN_SUBMIT_DELAY_SECONDS;
    }

    public function verifyRecaptcha(string $token, ?string $remoteIp): bool
    {
        return $this->recaptcha->verify($token, $remoteIp);
    }

    /**
     * @return 'hourly'|'daily'|null
     */
    public function checkRateLimits(User $user): ?string
    {
        $key = (string) ($user->getId() ?? 0);

        $hourly = $this->hourlyLimiter->create($key)->consume(1);
        if (!$hourly->isAccepted()) {
            return 'hourly';
        }

        $daily = $this->dailyLimiter->create($key)->consume(1);
        if (!$daily->isAccepted()) {
            return 'daily';
        }

        return null;
    }
}
