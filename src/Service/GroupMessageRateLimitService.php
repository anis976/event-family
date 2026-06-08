<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class GroupMessageRateLimitService
{
    public function __construct(
        #[Autowire(service: 'limiter.group_message_hourly')]
        private readonly RateLimiterFactory $hourlyLimiter,
    ) {
    }

    public function assertCanSend(User $sender): void
    {
        $userId = $sender->getId();
        if (null === $userId) {
            throw new \DomainException('flash.message.rate_hourly_group');
        }

        $limit = $this->hourlyLimiter->create((string) $userId);
        if (!$limit->consume(1)->isAccepted()) {
            throw new \DomainException('flash.message.rate_hourly_group');
        }
    }
}
