<?php

declare(strict_types=1);

namespace App\Entity;

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
class Message
{
    public const int MAX_REPLIES = 2;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'authoredMessages')]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private User $author;

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

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

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

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function setAuthor(User $author): static
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, MessageRead>
     */
    public function getReads(): Collection
    {
        return $this->reads;
    }
}
