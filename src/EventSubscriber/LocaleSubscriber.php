<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\LocaleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LocaleService $localeService,
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
            KernelEvents::RESPONSE => [['onKernelResponse', -10]],
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $user = $this->security->getUser();
        $userEntity = $user instanceof User ? $user : null;

        $locale = $this->localeService->resolveLocale($request, $userEntity);
        $locale = $this->localeService->persistLocale($request, $locale, $userEntity);

        if (null !== $userEntity && $this->entityManager->getUnitOfWork()->isScheduledForUpdate($userEntity)) {
            $this->entityManager->flush();
        }

        $request->setLocale($locale);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->localeService->attachLocaleCookie(
            $event->getResponse(),
            $event->getRequest()->getLocale(),
        );
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $request = $event->getRequest();
        if (null === $request) {
            return;
        }

        $locale = $this->localeService->resolveLocale($request, $user);
        $this->localeService->persistLocale($request, $locale, $user);

        if ($this->entityManager->getUnitOfWork()->isScheduledForUpdate($user)) {
            $this->entityManager->flush();
        }

        $request->setLocale($locale);
    }
}
