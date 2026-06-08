<?php

declare(strict_types=1);

namespace App\Entity;

use App\Contract\EfAdminLabelInterface;
use App\Entity\Trait\AdminLabelTrait;
use App\Entity\Trait\TimestampableParisTrait;
use App\Repository\GroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GroupRepository::class)]
#[ORM\Table(name: 'ef_groups')]
#[ORM\UniqueConstraint(name: 'uniq_ef_groups_owner', columns: ['owner_id'])]
#[ORM\UniqueConstraint(name: 'uniq_ef_groups_name', columns: ['name'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['name'], message: 'group.name.unique', groups: ['Default', 'Admin'])]
class Group implements EfAdminLabelInterface
{
    use TimestampableParisTrait;
    use AdminLabelTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'group.name.required')]
    private string $name = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'group.family_name.required')]
    private string $familyName = '';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 500, maxMessage: 'group.description.max')]
    private ?string $description = null;

    /** Message système affiché en tête du fil de groupe (null = texte par défaut plateforme). */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $systemNoticeContent = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $systemNoticeUpdatedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'authoredGroups')]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'ownedGroups')]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $owner = null;

    /**
     * @var Collection<int, GroupMember>
     */
    #[ORM\OneToMany(targetEntity: GroupMember::class, mappedBy: 'group', orphanRemoval: true, cascade: ['persist'])]
    private Collection $groupMembers;

    /**
     * @var Collection<int, UserBan>
     */
    #[ORM\OneToMany(targetEntity: UserBan::class, mappedBy: 'relatedGroup', orphanRemoval: true)]
    private Collection $userBans;

    /**
     * @var Collection<int, GroupRequest>
     */
    #[ORM\OneToMany(targetEntity: GroupRequest::class, mappedBy: 'relatedGroup', orphanRemoval: true)]
    private Collection $groupRequests;

    /**
     * @var Collection<int, Message>
     */
    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'relatedGroup', orphanRemoval: true)]
    private Collection $messages;

    /**
     * @var Collection<int, Event>
     */
    #[ORM\OneToMany(targetEntity: Event::class, mappedBy: 'relatedGroup', orphanRemoval: true)]
    private Collection $events;

    public function __construct()
    {
        $this->groupMembers = new ArrayCollection();
        $this->userBans = new ArrayCollection();
        $this->groupRequests = new ArrayCollection();
        $this->messages = new ArrayCollection();
        $this->events = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getFamilyName(): string
    {
        return $this->familyName;
    }

    public function setFamilyName(string $familyName): static
    {
        $this->familyName = $familyName;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getSystemNoticeContent(): ?string
    {
        return $this->systemNoticeContent;
    }

    public function setSystemNoticeContent(?string $systemNoticeContent): static
    {
        $this->systemNoticeContent = $systemNoticeContent;

        return $this;
    }

    public function getSystemNoticeUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->systemNoticeUpdatedAt;
    }

    public function setSystemNoticeUpdatedAt(?\DateTimeImmutable $systemNoticeUpdatedAt): static
    {
        $this->systemNoticeUpdatedAt = $systemNoticeUpdatedAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
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

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @return Collection<int, GroupMember>
     */
    public function getGroupMembers(): Collection
    {
        return $this->groupMembers;
    }

    public function addGroupMember(GroupMember $groupMember): static
    {
        if (!$this->groupMembers->contains($groupMember)) {
            $this->groupMembers->add($groupMember);
            $groupMember->setGroup($this);
        }

        return $this;
    }

    public function removeGroupMember(GroupMember $groupMember): static
    {
        $this->groupMembers->removeElement($groupMember);

        return $this;
    }

    public function getDisplayLabel(): string
    {
        return trim($this->name.' '.$this->familyName);
    }

    public function getAdminLabel(): string
    {
        $label = $this->getDisplayLabel();

        return '' !== $label ? $label : 'Groupe #'.($this->id ?? '?');
    }

    /**
     * @return Collection<int, UserBan>
     */
    public function getUserBans(): Collection
    {
        return $this->userBans;
    }

    /**
     * @return Collection<int, GroupRequest>
     */
    public function getGroupRequests(): Collection
    {
        return $this->groupRequests;
    }

    public function addGroupRequest(GroupRequest $groupRequest): static
    {
        if (!$this->groupRequests->contains($groupRequest)) {
            $this->groupRequests->add($groupRequest);
            $groupRequest->setRelatedGroup($this);
        }

        return $this;
    }

    public function removeGroupRequest(GroupRequest $groupRequest): static
    {
        $this->groupRequests->removeElement($groupRequest);

        return $this;
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    /**
     * @return Collection<int, Event>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }
}
