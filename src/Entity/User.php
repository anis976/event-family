<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampableParisTrait;
use App\Repository\UserRepository;
use App\Util\ParisClock;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Validator\Constraints\UniqueUserFullName;
use App\Validator\Constraints\UniqueUserPseudo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'ef_users')]
#[ORM\UniqueConstraint(name: 'uniq_ef_users_email', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'uniq_ef_users_full_name', columns: ['first_name', 'last_name'])]
#[ORM\UniqueConstraint(name: 'uniq_ef_users_verification_token', columns: ['verification_token_hash'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'Cette adresse e-mail est déjà utilisée.', groups: ['Registration'])]
#[UniqueEntity(fields: ['firstName', 'lastName'], message: 'Ce prénom et ce nom sont déjà associés à un compte.', groups: ['Registration'])]
#[UniqueEntity(fields: ['pseudo'], message: 'Ce pseudo est déjà utilisé par un autre compte.', groups: ['Registration'], ignoreNull: true)]
#[UniqueUserFullName(groups: ['Profile'])]
#[UniqueUserPseudo(groups: ['Profile'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use TimestampableParisTrait;

    public const ROLE_USER = 'ROLE_USER';
    public const ROLE_MODERATOR = 'ROLE_MODERATOR';
    public const ROLE_ADMIN = 'ROLE_ADMIN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email = '';

    /**
     * @var list<string> Rôles site (ROLE_USER, ROLE_MODERATOR, ROLE_ADMIN)
     */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $pseudo = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $firstName = '';

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $lastName = '';

    #[ORM\Column(length: 5, options: ['default' => 'fr'])]
    private string $locale = 'fr';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(options: ['default' => false])]
    private bool $isVerified = false;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $verificationTokenHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $verificationTokenExpiresAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $passwordChangeTokenHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $passwordChangeTokenExpiresAt = null;

    /** Hash du nouveau mot de passe, appliqué après confirmation par e-mail. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pendingPasswordHash = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $passwordResetTokenHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $passwordResetTokenExpiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $passwordResetRequestedAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $accountDeletionTokenHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $accountDeletionTokenExpiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $accountDeletionRequestedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isBanned = false;

    /**
     * @var Collection<int, Group>
     */
    #[ORM\OneToMany(targetEntity: Group::class, mappedBy: 'author')]
    private Collection $authoredGroups;

    /**
     * @var Collection<int, Group>
     */
    #[ORM\OneToMany(targetEntity: Group::class, mappedBy: 'owner')]
    private Collection $ownedGroups;

    /**
     * @var Collection<int, GroupMember>
     */
    #[ORM\OneToMany(targetEntity: GroupMember::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $groupMemberships;

    /**
     * @var Collection<int, UserBan>
     */
    #[ORM\OneToMany(targetEntity: UserBan::class, mappedBy: 'bannedUser', orphanRemoval: true)]
    private Collection $receivedBans;

    /**
     * @var Collection<int, UserBan>
     */
    #[ORM\OneToMany(targetEntity: UserBan::class, mappedBy: 'author')]
    private Collection $authoredBans;

    /**
     * @var Collection<int, GroupRequest>
     */
    #[ORM\OneToMany(targetEntity: GroupRequest::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $groupRequests;

    /**
     * @var Collection<int, Message>
     */
    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'author', orphanRemoval: true)]
    private Collection $authoredMessages;

    /**
     * @var Collection<int, Message>
     */
    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'recipient', orphanRemoval: true)]
    private Collection $receivedMessages;

    public function __construct()
    {
        $now = ParisClock::now();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->roles = [self::ROLE_USER];
        $this->authoredGroups = new ArrayCollection();
        $this->ownedGroups = new ArrayCollection();
        $this->groupMemberships = new ArrayCollection();
        $this->receivedBans = new ArrayCollection();
        $this->authoredBans = new ArrayCollection();
        $this->groupRequests = new ArrayCollection();
        $this->authoredMessages = new ArrayCollection();
        $this->receivedMessages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = self::ROLE_USER;

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
    }

    public function getPseudo(): ?string
    {
        return $this->pseudo;
    }

    public function setPseudo(?string $pseudo): static
    {
        $pseudo = null !== $pseudo ? trim($pseudo) : null;
        $this->pseudo = ('' === $pseudo) ? null : $pseudo;

        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getDisplayName(): string
    {
        if (null !== $this->pseudo && '' !== trim($this->pseudo)) {
            return $this->pseudo;
        }

        return trim($this->firstName.' '.$this->lastName);
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getVerificationTokenHash(): ?string
    {
        return $this->verificationTokenHash;
    }

    public function setVerificationTokenHash(?string $verificationTokenHash): static
    {
        $this->verificationTokenHash = $verificationTokenHash;

        return $this;
    }

    public function getVerificationTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->verificationTokenExpiresAt;
    }

    public function setVerificationTokenExpiresAt(?\DateTimeImmutable $verificationTokenExpiresAt): static
    {
        $this->verificationTokenExpiresAt = $verificationTokenExpiresAt;

        return $this;
    }

    public function getPasswordChangeTokenHash(): ?string
    {
        return $this->passwordChangeTokenHash;
    }

    public function setPasswordChangeTokenHash(?string $passwordChangeTokenHash): static
    {
        $this->passwordChangeTokenHash = $passwordChangeTokenHash;

        return $this;
    }

    public function getPasswordChangeTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->passwordChangeTokenExpiresAt;
    }

    public function setPasswordChangeTokenExpiresAt(?\DateTimeImmutable $passwordChangeTokenExpiresAt): static
    {
        $this->passwordChangeTokenExpiresAt = $passwordChangeTokenExpiresAt;

        return $this;
    }

    public function getPendingPasswordHash(): ?string
    {
        return $this->pendingPasswordHash;
    }

    public function setPendingPasswordHash(?string $pendingPasswordHash): static
    {
        $this->pendingPasswordHash = $pendingPasswordHash;

        return $this;
    }

    public function clearPendingPasswordChange(): static
    {
        $this->passwordChangeTokenHash = null;
        $this->passwordChangeTokenExpiresAt = null;
        $this->pendingPasswordHash = null;

        return $this;
    }

    public function getPasswordResetTokenHash(): ?string
    {
        return $this->passwordResetTokenHash;
    }

    public function setPasswordResetTokenHash(?string $passwordResetTokenHash): static
    {
        $this->passwordResetTokenHash = $passwordResetTokenHash;

        return $this;
    }

    public function getPasswordResetTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->passwordResetTokenExpiresAt;
    }

    public function setPasswordResetTokenExpiresAt(?\DateTimeImmutable $passwordResetTokenExpiresAt): static
    {
        $this->passwordResetTokenExpiresAt = $passwordResetTokenExpiresAt;

        return $this;
    }

    public function getPasswordResetRequestedAt(): ?\DateTimeImmutable
    {
        return $this->passwordResetRequestedAt;
    }

    public function setPasswordResetRequestedAt(?\DateTimeImmutable $passwordResetRequestedAt): static
    {
        $this->passwordResetRequestedAt = $passwordResetRequestedAt;

        return $this;
    }

    public function clearPasswordReset(): static
    {
        $this->passwordResetTokenHash = null;
        $this->passwordResetTokenExpiresAt = null;
        $this->passwordResetRequestedAt = null;

        return $this;
    }

    public function getAccountDeletionTokenHash(): ?string
    {
        return $this->accountDeletionTokenHash;
    }

    public function setAccountDeletionTokenHash(?string $accountDeletionTokenHash): static
    {
        $this->accountDeletionTokenHash = $accountDeletionTokenHash;

        return $this;
    }

    public function getAccountDeletionTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->accountDeletionTokenExpiresAt;
    }

    public function setAccountDeletionTokenExpiresAt(?\DateTimeImmutable $accountDeletionTokenExpiresAt): static
    {
        $this->accountDeletionTokenExpiresAt = $accountDeletionTokenExpiresAt;

        return $this;
    }

    public function getAccountDeletionRequestedAt(): ?\DateTimeImmutable
    {
        return $this->accountDeletionRequestedAt;
    }

    public function setAccountDeletionRequestedAt(?\DateTimeImmutable $accountDeletionRequestedAt): static
    {
        $this->accountDeletionRequestedAt = $accountDeletionRequestedAt;

        return $this;
    }

    public function clearAccountDeletion(): static
    {
        $this->accountDeletionTokenHash = null;
        $this->accountDeletionTokenExpiresAt = null;
        $this->accountDeletionRequestedAt = null;

        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function isBanned(): bool
    {
        return $this->isBanned;
    }

    public function setIsBanned(bool $isBanned): static
    {
        $this->isBanned = $isBanned;

        return $this;
    }

    /**
     * @return Collection<int, Group>
     */
    public function getAuthoredGroups(): Collection
    {
        return $this->authoredGroups;
    }

    /**
     * @return Collection<int, Group>
     */
    public function getOwnedGroups(): Collection
    {
        return $this->ownedGroups;
    }

    /**
     * @return Collection<int, GroupMember>
     */
    public function getGroupMemberships(): Collection
    {
        return $this->groupMemberships;
    }

    /**
     * @return Collection<int, UserBan>
     */
    public function getReceivedBans(): Collection
    {
        return $this->receivedBans;
    }

    /**
     * @return Collection<int, UserBan>
     */
    public function getAuthoredBans(): Collection
    {
        return $this->authoredBans;
    }

    /**
     * @return Collection<int, GroupRequest>
     */
    public function getGroupRequests(): Collection
    {
        return $this->groupRequests;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getAuthoredMessages(): Collection
    {
        return $this->authoredMessages;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getReceivedMessages(): Collection
    {
        return $this->receivedMessages;
    }
}
