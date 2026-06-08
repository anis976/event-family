<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Évite les erreurs JS de la Web Debug Toolbar (renderAjaxRequests / null.style)
 * lors des requêtes fetch légères (Turbo + polling + marquage lu).
 *
 * @see https://github.com/symfony/symfony/issues/44142
 */
#[When(env: 'dev')]
final class DevLightweightRouteProfilerSubscriber implements EventSubscriberInterface
{
    private const string COLLECT_ATTRIBUTE = '_profiler_collect';

    /** @var list<string> */
    private const array ROUTES_WITHOUT_PROFILER = [
        'app_messages_read',
        'app_invitations_counts',
        'app_session_activity',
    ];

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');
        if (!\is_string($route) || !\in_array($route, self::ROUTES_WITHOUT_PROFILER, true)) {
            return;
        }

        $event->getRequest()->attributes->set(self::COLLECT_ATTRIBUTE, false);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 4096],
        ];
    }
}
