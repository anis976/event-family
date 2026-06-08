<?php

declare(strict_types=1);

namespace App\Entity;

use App\Contract\EfAdminLabelInterface;
use App\Entity\Trait\AdminLabelTrait;
use App\Enum\PlatformNoticeVariant;
use App\Repository\MessageRepository;
use App\Util\ParisClock;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'ef_messages')]
#[ORM\Index(name: 'idx_ef_messages_private', columns: ['author_id', 'recipient_id'])]
#[ORM\Index(name: 'idx_ef_messages_group', columns: ['related_group_id', 'created_at'])]
#[ORM\HasLifecycleCallbacks]
class Message implements EfAdminLabelInterface
{
    use AdminLabelTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'authoredMessages')]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?User $author = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'receivedMessages')]
    #[ORM\JoinColumn(name: 'recipient_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?User $recipient = null;

    #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(name: 'related_group_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Group $relatedGroup = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'replies')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?self $parent = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent', orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $replies;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 5000)]
    private string $content = '';

    #[ORM\Column(options: ['default' => false])]
    private bool $isStaffAnnouncement = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $isPlatformNotice = false;

    #[ORM\Column(length: 20, nullable: true, enumType: PlatformNoticeVariant::class)]
    private ?PlatformNoticeVariant $platformNoticeVariant = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $authorHiddenAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $recipientHiddenAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $repliesClosedAt = null;

    /**
     * @var Collection<int, MessageRead>
     */
    #[ORM\OneToMany(targetEntity: MessageRead::class, mappedBy: 'message', orphanRemoval: true, cascade: ['remove'])]
    private Collection $reads;

    public function __construct()
    {
        $this->replies = new ArrayCollection();
        $this->reads = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt ??= ParisClock::now();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getRecipient(): ?User
    {
        return $this->recipient;
    }

    public function setRecipient(?User $recipient): static
    {
        $this->recipient = $recipient;

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

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getReplies(): Collection
    {
        return $this->replies;
    }

    public function addReply(self $reply): static
    {
        if (!$this->replies->contains($reply)) {
            $this->replies->add($reply);
            $reply->setParent($this);
        }

        return $this;
    }

    public function isGroupMessage(): bool
    {
        return null !== $this->relatedGroup;
    }

    public function isPrivateMessage(): bool
    {
        return null === $this->relatedGroup && null !== $this->recipient;
    }

    public function isRoot(): bool
    {
        return null === $this->parent;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function isStaffAnnouncement(): bool
    {
        return $this->isStaffAnnouncement;
    }

    public function setIsStaffAnnouncement(bool $isStaffAnnouncement): static
    {
        $this->isStaffAnnouncement = $isStaffAnnouncement;

        return $this;
    }

    public function isPlatformNotice(): bool
    {
        return $this->isPlatformNotice;
    }

    public function setIsPlatformNotice(bool $isPlatformNotice): static
    {
        $this->isPlatformNotice = $isPlatformNotice;

        return $this;
    }

    public function getPlatformNoticeVariant(): ?PlatformNoticeVariant
    {
        return $this->platformNoticeVariant;
    }

    public function setPlatformNoticeVariant(?PlatformNoticeVariant $platformNoticeVariant): static
    {
        $this->platformNoticeVariant = $platformNoticeVariant;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getAuthorHiddenAt(): ?\DateTimeImmutable
    {
        return $this->authorHiddenAt;
    }

    public function getRecipientHiddenAt(): ?\DateTimeImmutable
    {
        return $this->recipientHiddenAt;
    }

    public function getRepliesClosedAt(): ?\DateTimeImmutable
    {
        return $this->repliesClosedAt;
    }

    public function areRepliesClosed(): bool
    {
        return null !== $this->repliesClosedAt;
    }

    public function isHiddenFor(User $user): bool
    {
        $userId = $user->getId();
        if (null === $userId) {
            return false;
        }

        if ($this->author?->getId() === $userId) {
            return null !== $this->authorHiddenAt;
        }

        if ($this->recipient?->getId() === $userId) {
            return null !== $this->recipientHiddenAt;
        }

        return false;
    }

    public function hideFor(User $user): void
    {
        $now = ParisClock::now();
        $userId = $user->getId();

        if (null === $userId) {
            return;
        }

        if ($this->author?->getId() === $userId) {
            $this->authorHiddenAt = $now;
        } elseif ($this->recipient?->getId() === $userId) {
            $this->recipientHiddenAt = $now;
        } else {
            return;
        }

        if ($this->isPrivateMessage() && $this->isRoot()) {
            $this->repliesClosedAt = $now;
        }
    }

    /**
     * @return Collection<int, MessageRead>
     */
    public function getReads(): Collection
    {
        return $this->reads;
    }

    /** Clé de traduction admin (admin.crud.message.channel.*). */
    public function getChannelLabelKey(): string
    {
        if ($this->isPlatformNotice) {
            return 'admin.crud.message.channel.platform_notice';
        }

        if ($this->isStaffAnnouncement) {
            return 'admin.crud.message.channel.staff_announcement';
        }

        if ($this->isGroupMessage()) {
            return null !== $this->parent
                ? 'admin.crud.message.channel.group_reply'
                : 'admin.crud.message.channel.group';
        }

        if ($this->isPrivateMessage()) {
            return null !== $this->parent
                ? 'admin.crud.message.channel.private_reply'
                : 'admin.crud.message.channel.private';
        }

        return 'admin.crud.message.channel.other';
    }

    /** Affichage admin EasyAdmin (fiche détail litige) — contenu fourni par MessageThreadContextFormatter. */
    public function getThreadContext(): string
    {
        return '';
    }

    public function getAdminLabel(): string
    {
        $excerpt = mb_strlen($this->content) > 60
            ? mb_substr($this->content, 0, 60).'…'
            : $this->content;

        return sprintf('#%s — %s', $this->id ?? '?', $excerpt);
    }

}
