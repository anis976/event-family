<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\GoogleOAuthCompleteFormType;
use App\Service\GoogleOAuthRegistrationCancelService;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class GoogleOAuthController extends AbstractAppController
{
    /**
     * Callback OAuth Google — traité par {@see \App\Security\GoogleAuthenticator}.
     */
    #[Route('/connect/google/check', name: 'connect_google_check', methods: ['GET'])]
    public function connectCheck(): never
    {
        throw new \LogicException('Cette route est gérée par le pare-feu de sécurité (GoogleAuthenticator).');
    }

    #[Route('/connect/google/go', name: 'app_connect_google_start', methods: ['GET'])]
    public function connectStart(Request $request, ClientRegistry $clientRegistry): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        return $clientRegistry->getClient('google')->redirect([], []);
    }

    #[Route('/inscription/google/terminer', name: 'app_google_oauth_complete', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function completeRegistration(
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($user->isOAuthRegistrationComplete()) {
            return $this->redirectToRoute('app_home');
        }

        $form = $this->createForm(GoogleOAuthCompleteFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setOAuthRegistrationComplete(true);

            try {
                $entityManager->flush();
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
                $this->addErrorFlash('auth.google.profile_unique_conflict');

                return $this->render('security/google_oauth_complete.html.twig', [
                    'completeForm' => $form,
                ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            $this->addSuccessFlash('auth.google.registration_complete');

            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/google_oauth_complete.html.twig', [
            'completeForm' => $form,
        ]);
    }

    #[Route('/inscription/google/annuler', name: 'app_google_oauth_cancel', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function cancelIncompleteRegistration(
        Request $request,
        Security $security,
        GoogleOAuthRegistrationCancelService $cancelService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('google_oauth_cancel', (string) $request->request->get('_token'))) {
            $this->addErrorFlash('auth.google.cancel_csrf_invalid');

            return $this->redirectToRoute('app_google_oauth_complete');
        }

        if (!$cancelService->canCancel($user)) {
            return $this->redirectToRoute('app_home');
        }

        $cancelService->cancel($user);
        $security->logout(false);
        $request->getSession()->invalidate();

        $this->addSuccessFlash('auth.google.registration_cancelled');

        return $this->redirectToRoute('app_login');
    }
}
