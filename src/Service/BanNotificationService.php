<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Group;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BanNotificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly TransactionalEmailHelper $emailHelper,
        private readonly TranslatorInterface $translator,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $mailerFrom,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendWarningEmail(User $user, int $step, string $reason, ?Group $group): void
    {
        $email = $this->emailHelper->prepare(
            (new TemplatedEmail())
                ->from(Address::create($this->mailerFrom))
                ->to($user->getEmail())
                ->subject($this->buildEmailSubject($step, $user))
                ->htmlTemplate('emails/ban_warning.html.twig'),
            $user,
            context: [
                'user' => $user,
                'step' => $step,
                'maxSteps' => BanEscalationService::MAX_BANS_BEFORE_DELETION,
                'reason' => $reason,
                'group' => $group,
                'isFinalWarning' => 2 === $step,
                'isDeletion' => 3 === $step,
            ],
        );

        $this->mailer->send($email);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendAccountDeletedEmail(string $originalEmail, string $reason, ?Group $group, int $banCount, string $locale = 'fr'): void
    {
        $email = $this->emailHelper->prepare(
            (new TemplatedEmail())
                ->from(Address::create($this->mailerFrom))
                ->to($originalEmail)
                ->subject($this->emailHelper->trans('email.ban_deleted.subject', locale: $locale))
                ->htmlTemplate('emails/ban_account_deleted.html.twig'),
            locale: $locale,
            context: [
                'reason' => $reason,
                'group' => $group,
                'banCount' => $banCount,
            ],
        );

        $this->mailer->send($email);
    }

    public function buildPrivateNoticeContent(int $step, string $reason, ?Group $group, ?User $user = null): string
    {
        $locale = $this->emailHelper->resolveLocale($user);
        $groupLine = null !== $group
            ? $this->translator->trans('notice.ban.group_line', [
                '%name%' => $group->getName() ?? '',
                '%family%' => $group->getFamilyName() ?? '',
            ], 'messages', $locale)
            : $this->translator->trans('notice.ban.group_unknown', [], 'messages', $locale);

        return match ($step) {
            1 => $this->translator->trans('notice.ban.step1', [
                '%group_line%' => $groupLine,
                '%reason%' => $reason,
            ], 'messages', $locale),
            2 => $this->translator->trans('notice.ban.step2', [
                '%group_line%' => $groupLine,
                '%reason%' => $reason,
            ], 'messages', $locale),
            default => $this->translator->trans('notice.ban.step3', [
                '%group_line%' => $groupLine,
                '%reason%' => $reason,
            ], 'messages', $locale),
        };
    }

    private function buildEmailSubject(int $step, User $user): string
    {
        return match ($step) {
            1 => $this->emailHelper->trans('email.ban_warning.subject_step1', [], $user),
            2 => $this->emailHelper->trans('email.ban_warning.subject_step2', [], $user),
            default => $this->emailHelper->trans('email.ban_warning.subject_step3', [], $user),
        };
    }
}
