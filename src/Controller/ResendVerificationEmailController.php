<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ResendVerificationEmailController extends AbstractAppController
{
    private const string CSRF_TOKEN_ID = 'resend_verification';

    #[Route('/verification/resend', name: 'app_resend_verification_email', methods: ['POST'])]
    public function resend(
        Request $request,
        CsrfTokenManagerInterface $csrfTokenManager,
        ValidatorInterface $validator,
        UserRepository $userRepository,
        EmailVerificationService $emailVerification,
        EntityManagerInterface $entityManager,
        #[Autowire(service: 'limiter.resend_verification')]
        RateLimiterFactory $resendVerificationLimiter,
    ): Response {
        if (!$csrfTokenManager->isTokenValid(
            new CsrfToken(self::CSRF_TOKEN_ID, (string) $request->request->get('_token')),
        )) {
            $this->addErrorFlash('flash.session_expired');

            return $this->redirectToRoute('app_login');
        }

        $limiter = $resendVerificationLimiter->create($request->getClientIp() ?? 'unknown');
        $rateLimit = $limiter->consume(1);

        if (!$rateLimit->isAccepted()) {
            $this->addWarningFlash($this->trans('auth.resend_verification_rate_limited'));

            return $this->redirectToRoute('app_login');
        }

        $email = mb_strtolower(trim((string) $request->request->get('email', '')));

        $violations = $validator->validate($email, [
            new Assert\NotBlank(message: 'ui.auth.form.validation.email_required'),
            new Assert\Email(message: 'ui.auth.form.validation.email_invalid'),
        ]);

        if (count($violations) > 0) {
            $this->addErrorFlash($violations->get(0)->getMessage());

            return $this->redirectToRoute('app_login', ['email' => $email]);
        }

        $user = $userRepository->findOneBy(['email' => $email]);

        if (null !== $user && !$user->isVerified() && !$user->isBanned()) {
            try {
                $emailVerification->sendVerificationEmail($user);
                $entityManager->flush();
            } catch (TransportExceptionInterface) {
                $this->addErrorFlash('flash.verification.email_send_failed');

                return $this->redirectToRoute('app_login', ['email' => $email]);
            }
        }

        // Message générique : ne révèle pas si le compte existe (sécurité).
        $this->addSuccessFlash($this->trans('auth.resend_verification_sent'));

        return $this->redirectToRoute('app_login', ['email' => $email]);
    }
}
