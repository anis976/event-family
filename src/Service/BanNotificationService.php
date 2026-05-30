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

final class BanNotificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $mailerFrom,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendWarningEmail(User $user, int $step, string $reason, ?Group $group): void
    {
        $email = (new TemplatedEmail())
            ->from(Address::create($this->mailerFrom))
            ->to($user->getEmail())
            ->subject($this->buildEmailSubject($step))
            ->htmlTemplate('emails/ban_warning.html.twig')
            ->context([
                'user' => $user,
                'step' => $step,
                'maxSteps' => BanEscalationService::MAX_BANS_BEFORE_DELETION,
                'reason' => $reason,
                'group' => $group,
                'isFinalWarning' => 2 === $step,
                'isDeletion' => 3 === $step,
            ]);

        $this->mailer->send($email);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendAccountDeletedEmail(string $originalEmail, string $reason, ?Group $group, int $banCount): void
    {
        $email = (new TemplatedEmail())
            ->from(Address::create($this->mailerFrom))
            ->to($originalEmail)
            ->subject('EventFamily — Ton compte a été supprimé')
            ->htmlTemplate('emails/ban_account_deleted.html.twig')
            ->context([
                'reason' => $reason,
                'group' => $group,
                'banCount' => $banCount,
            ]);

        $this->mailer->send($email);
    }

    public function buildPrivateNoticeContent(int $step, string $reason, ?Group $group): string
    {
        $groupLine = null !== $group
            ? sprintf('Groupe concerné : %s (%s).', $group->getName(), $group->getFamilyName())
            : 'Groupe concerné : non précisé.';

        if (1 === $step) {
            return <<<TEXT
Tu as été banni d'un groupe sur EventFamily. Il s'agit de ton premier avertissement (1/3).

{$groupLine}

Motif du bannissement :
{$reason}

En cas de nouveaux bannissements sur la plateforme, des sanctions supplémentaires s'appliqueront, jusqu'à la suppression définitive de ton compte au 3e bannissement.
TEXT;
        }

        if (2 === $step) {
            return <<<TEXT
Tu as de nouveau été banni d'un groupe sur EventFamily. Il s'agit de ton dernier avertissement (2/3).

{$groupLine}

Motif du bannissement :
{$reason}

Au prochain bannissement, ton compte EventFamily sera supprimé définitivement.
TEXT;
        }

        return <<<TEXT
Tu as atteint le 3e bannissement sur EventFamily. Ton compte va être supprimé définitivement.

{$groupLine}

Motif du bannissement :
{$reason}

Tu ne pourras plus te connecter avec ce compte. Un e-mail de confirmation t'a également été envoyé.
TEXT;
    }

    private function buildEmailSubject(int $step): string
    {
        return match ($step) {
            1 => 'EventFamily — Avertissement 1/3 suite à un bannissement',
            2 => 'EventFamily — Dernier avertissement 2/3',
            default => 'EventFamily — Suppression de ton compte (3/3)',
        };
    }
}
