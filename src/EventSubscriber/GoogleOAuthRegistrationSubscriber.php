<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Redirige les comptes Google dont le profil n'est pas finalisé vers l'écran de complétion.
 */
final class GoogleOAuthRegistrationSubscriber implements EventSubscriberInterface
{
    private const ALLOWED_ROUTES = [
        'app_google_oauth_complete',
        'app_google_oauth_cancel',
        'app_logout',
        'connect_google_check',
        'app_connect_google_start',
        'app_locale_switch',
        'app_cgu',
        'app_mentions',
    ];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 5],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        if (!\is_string($route) || \in_array($route, self::ALLOWED_ROUTES, true)) {
            return;
        }

        if (str_starts_with($route, '_') || str_starts_with($request->getPathInfo(), '/connect/')) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User || $user->isOAuthRegistrationComplete()) {
            return;
        }

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('app_google_oauth_complete'),
        ));
    }
}
