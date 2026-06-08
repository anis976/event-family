<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class ContactSpamGuardService
{
    public function __construct(
        private readonly RecaptchaVerifierService $recaptcha,
        #[Autowire(service: 'limiter.contact_form_hourly')]
        private readonly RateLimiterFactory $hourlyLimiter,
        #[Autowire(service: 'limiter.contact_form_daily')]
        private readonly RateLimiterFactory $dailyLimiter,
        #[Autowire('%ef.contact.min_submit_delay_seconds%')]
        private readonly int $minSubmitDelaySeconds = 3,
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

        if ($this->minSubmitDelaySeconds <= 0) {
            return false;
        }

        return (time() - $started) < $this->minSubmitDelaySeconds;
    }

    public function verifyRecaptcha(string $token, ?string $remoteIp): bool
    {
        return $this->recaptcha->verify($token, $remoteIp);
    }

    public function wasLastRecaptchaTokenEmpty(): bool
    {
        return $this->recaptcha->wasLastTokenEmpty();
    }

    public function hasRecaptchaHostnameMismatch(): bool
    {
        return $this->recaptcha->hasHostnameMismatch();
    }

    /**
     * @return list<string>
     */
    public function getLastRecaptchaErrorCodes(): array
    {
        return $this->recaptcha->getLastErrorCodes();
    }

    public function getLastRecaptchaDebugSummary(): string
    {
        return $this->recaptcha->getLastDebugSummary();
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
