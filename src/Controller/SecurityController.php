<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractAppController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $lastUsername = $authenticationUtils->getLastUsername()
            ?: (string) $request->query->get('email', '');

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET', 'POST'])]
    public function logout(): void
    {
        throw new \LogicException('This method is intercepted by the logout feature on the firewall.');
    }
}
