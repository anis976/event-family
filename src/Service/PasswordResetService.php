<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Util\ParisClock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Réinitialisation mot de passe oublié (lien unique, courte durée, pas d'énumération d'e-mails).
 */
final class PasswordResetService
{
    private const int TOKEN_TTL_HOURS = 1;

    /** Délai minimum entre deux e-mails de reset pour le même compte. */
    private const int MIN_SECONDS_BETWEEN_EMAILS = 300;

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $mailerFrom,
    ) {
    }

    /**
     * Toujours exécuter côté appelant (message générique) — n'indique pas si le compte existe.
     *
     * @throws TransportExceptionInterface uniquement si un e-mail aurait dû partir
     */
    public function requestReset(string $email): void
    {
        $user = $this->userRepository->findEligibleForPasswordReset($email);

        if (null === $user) {
            return;
        }

        $now = ParisClock::now();
        $lastRequest = $user->getPasswordResetRequestedAt();

        if (
            null !== $lastRequest
            && $lastRequest > $now->modify(sprintf('-%d seconds', self::MIN_SECONDS_BETWEEN_EMAILS))
        ) {
            return;
        }

        $plainToken = bin2hex(random_bytes(32));

        $user->clearPendingPasswordChange();
        $user->setPasswordResetTokenHash($this->hashToken($plainToken));
        $user->setPasswordResetTokenExpiresAt(
            $now->modify(sprintf('+%d hours', self::TOKEN_TTL_HOURS)),
        );
        $user->setPasswordResetRequestedAt($now);

        $resetUrl = $this->urlGenerator->generate(
            'app_reset_password',
            ['token' => $plainToken],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $emailMessage = (new TemplatedEmail())
            ->from(Address::create($this->mailerFrom))
            ->to($user->getEmail())
            ->subject('EventFamily — Réinitialisation de ton mot de passe')
            ->htmlTemplate('emails/password_reset.html.twig')
            ->context([
                'user' => $user,
                'resetUrl' => $resetUrl,
                'expiresHours' => self::TOKEN_TTL_HOURS,
            ]);

        $this->mailer->send($emailMessage);
    }

    public function findUserForValidToken(string $plainToken): ?User
    {
        if (!$this->isValidTokenFormat($plainToken)) {
            return null;
        }

        $user = $this->userRepository->findOneByPasswordResetTokenHash($this->hashToken($plainToken));

        if (null === $user) {
            return null;
        }

        $expiresAt = $user->getPasswordResetTokenExpiresAt();

        if (null === $expiresAt || $expiresAt < ParisClock::now()) {
            $user->clearPasswordReset();
            $this->entityManager->flush();

            return null;
        }

        return $user;
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function resetPassword(User $user, string $plainNewPassword): void
    {
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainNewPassword));
        $user->clearPasswordReset();
        $user->clearPendingPasswordChange();

        $this->entityManager->flush();

        $this->sendPasswordChangedNotification($user);
    }

    public function isValidTokenFormat(string $plainToken): bool
    {
        return 64 === strlen($plainToken) && ctype_xdigit($plainToken);
    }

    /**
     * Réduit les fuites par timing (énumération / charge).
     */
    public function applyAntiTimingDelay(): void
    {
        usleep(random_int(200_000, 500_000));
    }

    /**
     * @throws TransportExceptionInterface
     */
    private function sendPasswordChangedNotification(User $user): void
    {
        $emailMessage = (new TemplatedEmail())
            ->from(Address::create($this->mailerFrom))
            ->to($user->getEmail())
            ->subject('EventFamily — Ton mot de passe a été modifié')
            ->htmlTemplate('emails/password_reset_done.html.twig')
            ->context([
                'user' => $user,
                'changedAt' => ParisClock::now(),
            ]);

        $this->mailer->send($emailMessage);
    }

    private function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
