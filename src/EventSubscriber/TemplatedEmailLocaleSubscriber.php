<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\MessageEvent;

/**
 * Garantit ef_email_locale dans le contexte Twig (y compris worker Messenger async).
 */
final class TemplatedEmailLocaleSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            MessageEvent::class => ['onMessage', 100],
        ];
    }

    public function onMessage(MessageEvent $event): void
    {
        $message = $event->getMessage();

        if (!$message instanceof TemplatedEmail) {
            return;
        }

        $context = $message->getContext();
        if (isset($context['ef_email_locale']) && \is_string($context['ef_email_locale'])) {
            return;
        }

        $locale = $message->getLocale();
        if (!\is_string($locale) || !\in_array($locale, ['fr', 'en'], true)) {
            $user = $context['user'] ?? null;
            $locale = $user instanceof User ? $user->getLocale() : 'fr';
        }

        if (!\in_array($locale, ['fr', 'en'], true)) {
            $locale = 'fr';
        }

        if (null === $message->getLocale()) {
            $message->locale($locale);
        }

        $message->context(array_merge($context, ['ef_email_locale' => $locale]));
    }
}
