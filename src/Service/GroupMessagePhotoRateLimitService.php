<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class GroupMessagePhotoRateLimitService
{
    public function __construct(
        #[Autowire(service: 'limiter.group_message_photo_hourly')]
        private readonly RateLimiterFactory $hourlyLimiter,
        #[Autowire(service: 'limiter.group_message_photo_daily')]
        private readonly RateLimiterFactory $dailyLimiter,
    ) {
    }

    public function assertCanUpload(User $sender, int $photoCount): void
    {
        if ($photoCount <= 0) {
            return;
        }

        $userId = $sender->getId();
        if (null === $userId) {
            throw new \DomainException('flash.message.photo_rate_hourly');
        }

        $key = (string) $userId;

        if (!$this->hourlyLimiter->create($key)->consume($photoCount)->isAccepted()) {
            throw new \DomainException('flash.message.photo_rate_hourly');
        }

        if (!$this->dailyLimiter->create($key)->consume($photoCount)->isAccepted()) {
            throw new \DomainException('flash.message.photo_rate_daily');
        }
    }
}
