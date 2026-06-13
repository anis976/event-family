<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Locale, sujets et en-têtes des e-mails transactionnels (selon préférence utilisateur).
 */
final class TransactionalEmailHelper
{
    /** Locale des e-mails reçus par l'administration (toujours en français). */
    public const string ADMIN_LOCALE = 'fr';

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function resolveLocale(?User $user = null, ?string $locale = null): string
    {
        $resolved = $locale ?? $user?->getLocale() ?? 'fr';

        return \in_array($resolved, ['fr', 'en'], true) ? $resolved : 'fr';
    }

    /**
     * @param array<string, int|float|string> $parameters
     */
    public function trans(string $id, array $parameters = [], ?User $user = null, ?string $locale = null): string
    {
        return $this->translator->trans($id, $parameters, 'messages', $this->resolveLocale($user, $locale));
    }

    /**
     * @param array<string, mixed> $context
     */
    public function prepare(TemplatedEmail $email, ?User $user = null, ?string $locale = null, array $context = []): TemplatedEmail
    {
        $resolvedLocale = $this->resolveLocale($user, $locale);

        return $email
            ->locale($resolvedLocale)
            ->context(array_merge($context, ['ef_email_locale' => $resolvedLocale]));
    }

    /**
     * @param array<string, int|float|string> $parameters
     */
    public function transForAdmin(string $id, array $parameters = []): string
    {
        return $this->trans($id, $parameters, locale: self::ADMIN_LOCALE);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function prepareForAdmin(TemplatedEmail $email, array $context = []): TemplatedEmail
    {
        return $this->prepare($email, locale: self::ADMIN_LOCALE, context: $context);
    }

    /**
     * En-têtes recommandés pour les notifications membres (MP, etc.) — limite le classement spam.
     */
    public function applyMemberNotificationHeaders(
        TemplatedEmail $email,
        string $listUnsubscribeUrl,
    ): TemplatedEmail {
        $headers = $email->getHeaders();
        $headers->addTextHeader('List-Unsubscribe', '<'.$listUnsubscribeUrl.'>');
        $headers->addTextHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');

        return $email;
    }

    /**
     * En-têtes pour les messages du formulaire contact (admin) — multipart + Reply-To formaté côté appelant.
     */
    public function applyContactFormHeaders(TemplatedEmail $email): TemplatedEmail
    {
        $headers = $email->getHeaders();
        $headers->addTextHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');
        $headers->addTextHeader('X-RapproFam-Message-Type', 'contact-form');

        return $email;
    }
}
