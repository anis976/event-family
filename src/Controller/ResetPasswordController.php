<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ResetPasswordFormType;
use App\Service\PasswordResetService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class ResetPasswordController extends AbstractAppController
{
    #[Route('/reinitialiser-mot-de-passe/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function __invoke(
        string $token,
        Request $request,
        PasswordResetService $passwordReset,
        #[Autowire(service: 'limiter.password_reset_confirm')]
        RateLimiterFactory $passwordResetConfirmLimiter,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        if (!$passwordReset->isValidTokenFormat($token)) {
            $this->addErrorFlash($this->trans('auth.password_reset_invalid_token'));

            return $this->redirectToRoute('app_forgot_password');
        }

        $user = $passwordReset->findUserForValidToken($token);

        if (null === $user) {
            $this->addErrorFlash($this->trans('auth.password_reset_invalid_token'));

            return $this->redirectToRoute('app_forgot_password');
        }

        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $limiter = $passwordResetConfirmLimiter->create($request->getClientIp() ?? 'unknown');
            $rateLimit = $limiter->consume(1);

            if (!$rateLimit->isAccepted()) {
                $this->addWarningFlash($this->trans('auth.password_reset_rate_limited'));

                return $this->redirectToRoute('app_forgot_password');
            }

            $user = $passwordReset->findUserForValidToken($token);

            if (null === $user) {
                $this->addErrorFlash($this->trans('auth.password_reset_invalid_token'));

                return $this->redirectToRoute('app_forgot_password');
            }

            try {
                $passwordReset->resetPassword($user, (string) $form->get('plainPassword')->getData());
            } catch (TransportExceptionInterface) {
                $this->addSuccessFlash($this->trans('auth.password_reset_done_no_notify'));

                return $this->redirectToRoute('app_login');
            }

            $this->addSuccessFlash($this->trans('auth.password_reset_done'));

            return $this->redirectToRoute('app_login');
        }

        $response = null;
        if ($form->isSubmitted() && !$form->isValid()) {
            $response = new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->render('security/reset_password.html.twig', [
            'resetPasswordForm' => $form,
            'token' => $token,
        ], $response);
    }
}
