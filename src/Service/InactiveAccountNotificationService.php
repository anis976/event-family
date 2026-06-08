<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\PlatformNoticeVariant;
use App\Util\ParisClock;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

final class InactiveAccountNotificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly MessageService $messageService,
        private readonly LoggerInterface $logger,
        private readonly TransactionalEmailHelper $emailHelper,
        private readonly TranslatorInterface $translator,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $mailerFrom,
    ) {
    }

    public function sendWarning(User $user, int $step, bool $isVerifiedAccount): void
    {
        $this->sendWarningEmail($user, $step, $isVerifiedAccount);

        if ($isVerifiedAccount) {
            $this->messageService->sendPlatformPrivateNotice(
                $user,
                $this->buildPrivateNoticeContent($step, $isVerifiedAccount, $user),
                PlatformNoticeVariant::EventFamily,
            );
        }
    }

    public function sendDeletionNotice(string $originalEmail, User $user, bool $wasVerified): void
    {
        $locale = $this->emailHelper->resolveLocale($user);

        $email = $this->emailHelper->prepare(
            (new TemplatedEmail())
                ->from(Address::create($this->mailerFrom))
                ->to($originalEmail)
                ->subject($this->emailHelper->trans('email.inactive_deleted.subject', locale: $locale))
                ->htmlTemplate('emails/inactive_account_deleted.html.twig'),
            locale: $locale,
            context: [
                'deletedAt' => ParisClock::now(),
                'wasVerified' => $wasVerified,
            ],
        );

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->warning('Suppression inactivité : e-mail non envoyé.', [
                'userId' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendWarningEmail(User $user, int $step, bool $isVerifiedAccount): void
    {
        $email = $this->emailHelper->prepare(
            (new TemplatedEmail())
                ->from(Address::create($this->mailerFrom))
                ->to($user->getEmail())
                ->subject($this->buildEmailSubject($step, $isVerifiedAccount, $user))
                ->htmlTemplate('emails/inactive_account_warning.html.twig'),
            $user,
            context: [
                'user' => $user,
                'step' => $step,
                'isVerifiedAccount' => $isVerifiedAccount,
                'isFinalWarning' => $isVerifiedAccount ? 2 === $step : true,
            ],
        );

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->warning('Avertissement inactivité : e-mail non envoyé.', [
                'userId' => $user->getId(),
                'step' => $step,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildEmailSubject(int $step, bool $isVerifiedAccount, User $user): string
    {
        if (!$isVerifiedAccount) {
            return $this->emailHelper->trans('email.inactive_warning.subject_unverified', [], $user);
        }

        return match ($step) {
            1 => $this->emailHelper->trans('email.inactive_warning.subject_step1', [], $user),
            default => $this->emailHelper->trans('email.inactive_warning.subject_step2', [], $user),
        };
    }

    public function buildPrivateNoticeContent(int $step, bool $isVerifiedAccount, ?User $user = null): string
    {
        $locale = $this->emailHelper->resolveLocale($user);

        if (!$isVerifiedAccount) {
            return $this->translator->trans('notice.inactive.unverified', [], 'messages', $locale);
        }

        return match ($step) {
            1 => $this->translator->trans('notice.inactive.step1', [], 'messages', $locale),
            default => $this->translator->trans('notice.inactive.step2', [], 'messages', $locale),
        };
    }
}
