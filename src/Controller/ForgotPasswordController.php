<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ForgotPasswordFormType;
use App\Service\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

final class ForgotPasswordController extends AbstractAppController
{
    #[Route('/mot-de-passe-oublie', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        PasswordResetService $passwordReset,
        EntityManagerInterface $entityManager,
        #[Autowire(service: 'limiter.password_reset_request')]
        RateLimiterFactory $passwordResetRequestLimiter,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $form = $this->createForm(ForgotPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $passwordReset->applyAntiTimingDelay();

            $limiter = $passwordResetRequestLimiter->create($request->getClientIp() ?? 'unknown');
            $rateLimit = $limiter->consume(1);

            if (!$rateLimit->isAccepted()) {
                $this->addWarningFlash($this->trans('auth.password_reset_rate_limited'));

                return $this->redirectToRoute('app_forgot_password');
            }

            $email = mb_strtolower(trim((string) $form->get('email')->getData()));

            try {
                $passwordReset->requestReset($email);
                $entityManager->flush();
            } catch (TransportExceptionInterface) {
                $this->addErrorFlash($this->trans('auth.password_reset_email_failed'));

                return $this->render('security/forgot_password.html.twig', [
                    'forgotPasswordForm' => $form,
                ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            $this->addSuccessFlash($this->trans('auth.password_reset_email_sent'));

            return $this->redirectToRoute('app_login');
        }

        $response = null;
        if ($form->isSubmitted() && !$form->isValid()) {
            $response = new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->render('security/forgot_password.html.twig', [
            'forgotPasswordForm' => $form,
        ], $response);
    }
}
