<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\LocaleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LocaleController extends AbstractController
{
    #[Route('/locale/switch', name: 'app_locale_switch', methods: ['GET'])]
    public function switch(
        Request $request,
        LocaleService $localeService,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        $userEntity = $user instanceof User ? $user : null;

        $current = $localeService->resolveLocale($request, $userEntity);
        $next = $localeService->toggle($current);

        $localeService->persistLocale($request, $next, $userEntity);

        if (null !== $userEntity && $entityManager->getUnitOfWork()->isScheduledForUpdate($userEntity)) {
            $entityManager->flush();
        }

        $referer = $request->headers->get('Referer');
        $response = (\is_string($referer) && '' !== $referer)
            ? $this->redirect($referer)
            : $this->redirectToRoute('app_home');

        $localeService->attachLocaleCookie($response, $next);

        return $response;
    }
}
