<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Repository\UserBanRepository;
use App\Repository\UserRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Déconnecte un utilisateur suspendu (isBanned) même s'il avait déjà une session ouverte.
 */
final class PlatformBanAccessSubscriber implements EventSubscriberInterface
{
    private const REMEMBER_ME_COOKIE = 'REMEMBERME';

    /** @var list<string> */
    private const EXCLUDED_ROUTE_PREFIXES = [
        'app_login',
        'app_logout',
        'app_register',
        'app_forgot_password',
        'app_reset_password',
        'app_verify_email',
        'app_resend_verification',
        '_wdt',
        '_profiler',
    ];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UserRepository $userRepository,
        private readonly UserBanRepository $userBanRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($this->shouldSkip($request)) {
            return;
        }

        $tokenUser = $this->tokenStorage->getToken()?->getUser();
        if (!$tokenUser instanceof User) {
            return;
        }

        $freshUser = $this->userRepository->find($tokenUser->getId());
        if (null === $freshUser || !$this->isPlatformSuspended($freshUser)) {
            return;
        }

        $this->tokenStorage->setToken(null);

        $session = $request->getSession();
        if ($session->isStarted()) {
            $session->invalidate();
        }

        $response = new RedirectResponse(
            $this->urlGenerator->generate('app_login', ['suspended' => '1']),
        );

        $response->headers->clearCookie(
            self::REMEMBER_ME_COOKIE,
            '/',
            null,
            $request->isSecure(),
            true,
            Cookie::SAMESITE_LAX,
        );

        $event->setResponse($response);
    }

    private function shouldSkip(Request $request): bool
    {
        $route = (string) $request->attributes->get('_route', '');

        foreach (self::EXCLUDED_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return true;
            }
        }

        return str_starts_with($request->getPathInfo(), '/build/')
            || str_starts_with($request->getPathInfo(), '/assets/');
    }

    private function isPlatformSuspended(User $user): bool
    {
        return $user->isBanned() || $this->userBanRepository->hasActivePlatformBan($user);
    }
}
