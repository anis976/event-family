<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\EmailVerificationService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class EmailVerificationController extends AbstractAppController
{
    #[Route('/verify-email/{token}', name: 'app_verify_email', methods: ['GET'])]
    public function verify(string $token, EmailVerificationService $emailVerification): Response
    {
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            $this->addErrorFlash('flash.verification.invalid_link');

            return $this->redirectToRoute('app_login');
        }

        if ($emailVerification->verifyEmail($token)) {
            $this->addSuccessFlash('flash.verification.confirmed');

            return $this->redirectToRoute('app_login');
        }

        $this->addErrorFlash('flash.verification.expired');

        return $this->redirectToRoute('app_login');
    }
}
