<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\ContactFormType;
use App\Service\ContactMailService;
use App\Service\ContactSpamGuardService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractAppController
{
    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ContactMailService $contactMail,
        ContactSpamGuardService $spamGuard,
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
            'attr' => [
                'class' => 'ef-contact__form',
                'data-turbo' => 'false',
                'data-ef-contact-form' => '1',
                'data-recaptcha-site-key' => $recaptchaEnabled ? $recaptchaSiteKey : '',
            ],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($spamGuard->isHoneypotTriggered($form->get('companyWebsite')->getData())
                || $spamGuard->isSubmittedTooFast($form->get('_startedAt')->getData())) {
                return $this->fakeSuccessRedirect();
            }

            if ($recaptchaEnabled && !$spamGuard->verifyRecaptcha(
                (string) $form->get('recaptchaToken')->getData(),
                $request->getClientIp(),
            )) {
                $this->addErrorFlash('Vérification anti-spam échouée. Recharge la page et réessaie.');

                return $this->renderContact($form, $user, $recaptchaSiteKey, $recaptchaEnabled);
            }

            $rateLimit = $spamGuard->checkRateLimits($user);
            if ('hourly' === $rateLimit) {
                $this->addWarningFlash('Tu as atteint la limite de 5 messages par heure. Réessaie dans quelques minutes.');

                return $this->redirectToRoute('app_contact');
            }

            if ('daily' === $rateLimit) {
                $this->addWarningFlash('Tu as atteint la limite de 20 messages aujourd\'hui. Merci de réessayer demain.');

                return $this->redirectToRoute('app_contact');
            }

            $message = trim((string) $form->get('message')->getData());

            try {
                $contactMail->sendContactMessage($user, $message);
            } catch (TransportExceptionInterface) {
                $this->addErrorFlash('Impossible d\'envoyer ton message pour le moment. Réessaie plus tard.');

                return $this->renderContact($form, $user, $recaptchaSiteKey, $recaptchaEnabled, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $this->addSuccessFlash('Ton message a bien été envoyé. Nous te répondrons dès que possible.');

            return $this->redirectToRoute('app_contact');
        }

        return $this->renderContact($form, $user, $recaptchaSiteKey, $recaptchaEnabled);
    }

    private function fakeSuccessRedirect(): Response
    {
        $this->addSuccessFlash('Ton message a bien été envoyé. Nous te répondrons dès que possible.');

        return $this->redirectToRoute('app_contact');
    }

    private function renderContact(
        FormInterface $form,
        User $user,
        string $recaptchaSiteKey,
        bool $recaptchaEnabled,
        int $status = Response::HTTP_OK,
    ): Response {
        return $this->render('contact/index.html.twig', [
            'contactForm' => $form,
            'user' => $user,
            'recaptchaSiteKey' => $recaptchaEnabled ? $recaptchaSiteKey : '',
        ], new Response(status: $status));
    }
}
