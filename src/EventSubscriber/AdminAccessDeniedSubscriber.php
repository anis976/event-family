<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Enum\FlashType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Quand la session n'a plus ROLE_MODERATOR (ex. connexion user dans un autre onglet),
 * évite la page brute « Access Denied » et renvoie vers le site.
 */
final class AdminAccessDeniedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%ef.admin.path%')]
        private readonly string $adminPath,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 10],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $throwable = $event->getThrowable();
        if (!$throwable instanceof AccessDeniedException) {
            return;
        }

        $request = $event->getRequest();
        if (!$this->isAdminRequest($request)) {
            return;
        }

        // Refus ponctuel (ex. modérateur → action admin) : comportement Symfony habituel.
        if ($this->authorizationChecker->isGranted(User::ROLE_MODERATOR)) {
            return;
        }

        $homeUrl = $this->urlGenerator->generate('app_home');

        if ($request->isXmlHttpRequest()) {
            $event->setResponse(new JsonResponse(['redirect' => $homeUrl], Response::HTTP_FORBIDDEN));

            return;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        if (null !== $session) {
            $session->getFlashBag()->add(
                FlashType::Info->value,
                $this->translator->trans('admin.access.session_changed', [], 'messages'),
            );
        }

        $event->setResponse(new RedirectResponse($homeUrl));
    }

    private function isAdminRequest(Request $request): bool
    {
        $path = $request->getPathInfo();

        return str_starts_with($path, $this->adminPath.'/')
            || $path === $this->adminPath;
    }
}
