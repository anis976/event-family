<?php

declare(strict_types=1);

namespace App\Entity;

use App\Contract\EfAdminLabelInterface;
use App\Entity\Trait\AdminLabelTrait;
use App\Repository\UserBanRepository;
use App\Util\ParisClock;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserBanRepository::class)]
#[ORM\Table(name: 'ef_user_bans')]
#[ORM\Index(name: 'idx_ef_user_bans_user_group', columns: ['banned_user_id', 'related_group_id'])]
#[ORM\HasLifecycleCallbacks]
class UserBan implements EfAdminLabelInterface
{
    use AdminLabelTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'receivedBans')]
    #[ORM\JoinColumn(name: 'banned_user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'user_ban.banned_user.required')]
    private User $bannedUser;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'user_ban.reason.required')]
    #[Assert\Length(max: 2000, maxMessage: 'user_ban.reason.max')]
    private string $reason = '';

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /** Null = bannissement sans date de fin (actif jusqu'à levée manuelle). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'authoredBans')]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: 'userBans')]
    #[ORM\JoinColumn(name: 'related_group_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Group $relatedGroup = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = ParisClock::now();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBannedUser(): User
    {
        return $this->bannedUser;
    }

    public function setBannedUser(User $bannedUser): static
    {
        $this->bannedUser = $bannedUser;

        return $this;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(?\DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getRelatedGroup(): ?Group
    {
        return $this->relatedGroup;
    }

    public function setRelatedGroup(?Group $relatedGroup): static
    {
        $this->relatedGroup = $relatedGroup;

        return $this;
    }

    public function isActiveAt(?\DateTimeImmutable $at = null): bool
    {
        $at ??= ParisClock::now();

        if (null === $this->endsAt) {
            return true;
        }

        return $this->endsAt > $at;
    }

    public function getAdminLabel(): string
    {
        return sprintf('#%s — %s', $this->id ?? '?', $this->bannedUser->getAdminLabel());
    }
}
