<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('%ef.admin.path%/session', name: 'ef_admin_session')]
#[IsGranted(User::ROLE_MODERATOR)]
final class AdminSessionActivityController extends AbstractController
{
    private const SESSION_ADMIN_ACTIVITY_KEY = '_ef_admin_last_activity';

    /** Même clé que {@see SessionIdleSubscriber} — prolonge la session site pendant le travail admin. */
    private const SESSION_SITE_ACTIVITY_KEY = '_ef_last_activity';

    #[Route('/activite', name: '_activity', methods: ['POST'])]
    public function refreshActivity(Request $request, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        $token = (string) $request->request->get('_token', $request->headers->get('X-CSRF-Token', ''));

        if (!$this->isCsrfTokenValid('ef_admin_session_activity', $token)) {
            return $this->json(['status' => 'error'], Response::HTTP_FORBIDDEN);
        }

        $now = time();
        $session = $request->getSession();
        $session->set(self::SESSION_ADMIN_ACTIVITY_KEY, $now);
        $session->set(self::SESSION_SITE_ACTIVITY_KEY, $now);

        return $this->json([
            'status' => 'ok',
            'csrf_token' => $csrfTokenManager->getToken('ef_admin_session_activity')->getValue(),
            'logout_csrf_token' => $csrfTokenManager->getToken('logout')->getValue(),
        ]);
    }
}
