<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/session', name: 'app_session')]
#[IsGranted('ROLE_USER')]
final class SessionActivityController extends AbstractAppController
{
    private const SESSION_LAST_ACTIVITY_KEY = '_ef_last_activity';

    #[Route('/activite', name: '_activity', methods: ['POST'])]
    public function refreshActivity(Request $request): JsonResponse
    {
        $token = (string) $request->request->get('_token', $request->headers->get('X-CSRF-Token', ''));

        if (!$this->isCsrfTokenValid('session_activity', $token)) {
            return $this->json(['status' => 'error'], Response::HTTP_FORBIDDEN);
        }

        $request->getSession()->set(self::SESSION_LAST_ACTIVITY_KEY, time());

        return $this->json(['status' => 'ok']);
    }
}
