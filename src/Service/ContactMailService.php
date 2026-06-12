<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Util\ParisClock;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class ContactMailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly TransactionalEmailHelper $emailHelper,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $mailerFrom,
        #[Autowire('%env(CONTACT_RECIPIENT)%')]
        private readonly string $contactRecipient,
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function sendContactMessage(User $user, string $message): void
    {
        $email = $this->emailHelper->prepareForAdmin(
            (new TemplatedEmail())
                ->from(Address::create($this->mailerFrom))
                ->to(Address::create($this->contactRecipient))
                ->replyTo($user->getEmail())
                ->subject($this->emailHelper->transForAdmin('email.contact.subject', [
                    '%name%' => trim($user->getFirstName().' '.$user->getLastName()),
                ]))
                ->htmlTemplate('emails/contact_message.html.twig'),
            context: [
                'user' => $user,
                'message' => $message,
                'sentAt' => ParisClock::now(),
            ],
        );

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Échec envoi formulaire contact.', [
                'userId' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
