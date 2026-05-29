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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class EmailVerificationService
{
    private const int TOKEN_TTL_HOURS = 24;

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $mailerFrom,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function sendVerificationEmail(User $user): void
    {
        $plainToken = bin2hex(random_bytes(32));

        $user->setIsVerified(false);
        $user->setVerificationTokenHash($this->hashToken($plainToken));
        $user->setVerificationTokenExpiresAt(
            ParisClock::now()->modify(sprintf('+%d hours', self::TOKEN_TTL_HOURS)),
        );

        $verificationUrl = $this->urlGenerator->generate(
            'app_verify_email',
            ['token' => $plainToken],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $email = (new TemplatedEmail())
            ->from(Address::create($this->mailerFrom))
            ->to($user->getEmail())
            ->subject('EventFamily — Active ton compte')
            ->htmlTemplate('emails/verification.html.twig')
            ->context([
                'user' => $user,
                'verificationUrl' => $verificationUrl,
                'expiresHours' => self::TOKEN_TTL_HOURS,
            ]);

        $this->mailer->send($email);
    }

    public function verifyEmail(string $plainToken): bool
    {
        $user = $this->userRepository->findOneByVerificationTokenHash($this->hashToken($plainToken));

        if (null === $user) {
            return false;
        }

        $expiresAt = $user->getVerificationTokenExpiresAt();

        if (null === $expiresAt || $expiresAt < ParisClock::now()) {
            return false;
        }

        $user->setIsVerified(true);
        $user->setVerificationTokenHash(null);
        $user->setVerificationTokenExpiresAt(null);

        $this->entityManager->flush();

        return true;
    }

    private function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
