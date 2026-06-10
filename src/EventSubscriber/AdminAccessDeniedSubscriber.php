<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Controller\Admin\Crud\UserCrudController;
use App\Entity\User;
use App\Enum\FlashType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
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
 * Évite les pages brutes « Access Denied » dans l'admin :
 * - session sans rôle staff → retour site ;
 * - staff avec droits insuffisants sur une action → flash + retour liste admin.
 */
final class AdminAccessDeniedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AdminUrlGenerator $adminUrlGenerator,
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

        if (!$this->authorizationChecker->isGranted(User::ROLE_MODERATOR)) {
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

            return;
        }

        $message = $throwable->getMessage();
        if ('' === trim($message) || 'Access Denied.' === $message) {
            $message = $this->translator->trans('admin.access.denied', [], 'messages');
        }

        $redirectUrl = $this->resolveAdminRedirect($request);

        if ($request->isXmlHttpRequest()) {
            $event->setResponse(new JsonResponse([
                'error' => $message,
                'redirect' => $redirectUrl,
            ], Response::HTTP_FORBIDDEN));

            return;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        if (null !== $session) {
            $session->getFlashBag()->add(FlashType::Warning->value, $message);
        }

        $event->setResponse(new RedirectResponse($redirectUrl));
    }

    private function resolveAdminRedirect(Request $request): string
    {
        $referer = $request->headers->get('Referer');
        if (\is_string($referer) && '' !== $referer && $this->isAdminRequestUrl($referer)) {
            return $referer;
        }

        return $this->adminUrlGenerator
            ->setController(UserCrudController::class)
            ->setAction(Action::INDEX)
            ->generateUrl();
    }

    private function isAdminRequest(Request $request): bool
    {
        $path = $request->getPathInfo();

        return str_starts_with($path, $this->adminPath.'/')
            || $path === $this->adminPath;
    }

    private function isAdminRequestUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (!\is_string($path) || '' === $path) {
            return false;
        }

        return str_starts_with($path, $this->adminPath.'/')
            || $path === $this->adminPath;
    }
}
