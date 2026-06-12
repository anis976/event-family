<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\ContactFormType;
use App\Service\ContactMailService;
use App\Service\ContactSpamGuardService;
use App\Service\ContactWhatsAppService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractAppController
{
    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ContactMailService $contactMail,
        ContactSpamGuardService $spamGuard,
        ContactWhatsAppService $contactWhatsApp,
        ParameterBagInterface $parameters,
        #[Autowire('%env(RECAPTCHA_SITE_KEY)%')]
        string $recaptchaSiteKey,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $recaptchaEnabled = $spamGuard->isRecaptchaEnabled();

        $form = $this->createForm(ContactFormType::class, null, [
            'recaptcha_enabled' => $recaptchaEnabled,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($spamGuard->isHoneypotTriggered($form->get('companyWebsite')->getData())
                || $spamGuard->isSubmittedTooFast($form->get('_startedAt')->getData())) {
                return $this->fakeSuccessRedirect();
            }

            $recaptchaToken = $form->has('recaptchaToken')
                ? (string) $form->get('recaptchaToken')->getData()
                : '';
            if ('' === trim($recaptchaToken)) {
                $posted = $request->request->all('contact_form');
                $recaptchaToken = \is_array($posted) ? (string) ($posted['recaptchaToken'] ?? '') : '';
            }

            if ($recaptchaEnabled && !$spamGuard->verifyRecaptcha(
                $recaptchaToken,
                $request->getClientIp(),
            )) {
                if ($spamGuard->hasRecaptchaHostnameMismatch()) {
                    $this->addErrorFlash('flash.contact.spam_hostname_mismatch');
                } elseif ($spamGuard->wasLastRecaptchaTokenEmpty()) {
                    $this->addErrorFlash('flash.contact.spam_token_missing');
                } elseif ('dev' === $parameters->get('kernel.environment')) {
                    $this->addErrorFlash('flash.contact.spam_failed_debug', [
                        '%detail%' => $spamGuard->getLastRecaptchaDebugSummary(),
                    ]);
                } else {
                    $this->addErrorFlash('flash.contact.spam_failed');
                }

                return $this->renderContact($form, $user, $recaptchaSiteKey, $recaptchaEnabled, $contactWhatsApp);
            }

            $rateLimit = $spamGuard->checkRateLimits($user);
            if ('hourly' === $rateLimit) {
                $this->addWarningFlash('flash.contact.rate_hourly');

                return $this->redirectToRoute('app_contact');
            }

            if ('daily' === $rateLimit) {
                $this->addWarningFlash('flash.contact.rate_daily');

                return $this->redirectToRoute('app_contact');
            }

            $message = trim((string) $form->get('message')->getData());

            try {
                $contactMail->sendContactMessage($user, $message);
            } catch (\Throwable) {
                $this->addErrorFlash('flash.contact.send_failed');

                return $this->renderContact($form, $user, $recaptchaSiteKey, $recaptchaEnabled, $contactWhatsApp, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $this->addSuccessFlash('flash.contact.sent');

            return $this->redirectToRoute('app_contact');
        }

        return $this->renderContact($form, $user, $recaptchaSiteKey, $recaptchaEnabled, $contactWhatsApp);
    }

    private function fakeSuccessRedirect(): Response
    {
        $this->addSuccessFlash('flash.contact.sent');

        return $this->redirectToRoute('app_contact');
    }

    private function renderContact(
        FormInterface $form,
        User $user,
        string $recaptchaSiteKey,
        bool $recaptchaEnabled,
        ContactWhatsAppService $contactWhatsApp,
        int $status = Response::HTTP_OK,
    ): Response {
        return $this->render('contact/index.html.twig', [
            'contactForm' => $form,
            'user' => $user,
            'recaptchaSiteKey' => $recaptchaEnabled ? $recaptchaSiteKey : '',
            'whatsappEnabled' => $contactWhatsApp->isEnabled(),
            'whatsappDisplayNumber' => $contactWhatsApp->getDisplayNumber(),
            'whatsappChatUrl' => $contactWhatsApp->getChatUrl(),
        ], new Response(status: $status));
    }
}
