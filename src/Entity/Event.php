<?php

declare(strict_types=1);

namespace App\Entity;

use App\Contract\EfAdminLabelInterface;
use App\Entity\Trait\AdminLabelTrait;
use App\Entity\Trait\TimestampableParisTrait;
use App\Enum\EventKind;
use App\Enum\EventVisibility;
use App\Repository\EventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'ef_events')]
#[ORM\Index(name: 'idx_ef_events_start_date', columns: ['start_date'])]
#[ORM\Index(name: 'idx_ef_events_visibility', columns: ['visibility'])]
#[ORM\HasLifecycleCallbacks]
#[Assert\Callback(callback: 'validateDateRange')]
class Event implements EfAdminLabelInterface
{
    use TimestampableParisTrait;
    use AdminLabelTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'event.title.required')]
    #[Assert\Length(max: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000, maxMessage: 'event.description.max')]
    private ?string $description = null;

    #[ORM\Column(length: 50, enumType: EventKind::class)]
    private EventKind $kind = EventKind::Other;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $location = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(length: 20, enumType: EventVisibility::class)]
    private EventVisibility $visibility = EventVisibility::Group;

    #[ORM\Column(name: 'photo_cover', length: 255, nullable: true)]
    private ?string $photoCover = null;

    #[ORM\Column(name: 'photo_detail', length: 255, nullable: true)]
    private ?string $photoDetail = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'authoredEvents')]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\ManyToOne(targetEntity: Group::class, inversedBy: 'events')]
    #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'event.group.required')]
    private ?Group $relatedGroup = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

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

    public function getKind(): EventKind
    {
        return $this->kind;
    }

    public function setKind(EventKind $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getVisibility(): EventVisibility
    {
        return $this->visibility;
    }

    public function setVisibility(EventVisibility $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function getPhotoCover(): ?string
    {
        return $this->photoCover;
    }

    public function setPhotoCover(?string $photoCover): static
    {
        $this->photoCover = $photoCover;

        return $this;
    }

    public function getPhotoDetail(): ?string
    {
        return $this->photoDetail;
    }

    public function setPhotoDetail(?string $photoDetail): static
    {
        $this->photoDetail = $photoDetail;

        return $this;
    }

    public function hasPhotoCover(): bool
    {
        return null !== $this->photoCover && '' !== $this->photoCover;
    }

    public function hasPhotoDetail(): bool
    {
        return null !== $this->photoDetail && '' !== $this->photoDetail;
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

    public function getRelatedGroup(): ?Group
    {
        return $this->relatedGroup;
    }

    public function setRelatedGroup(?Group $relatedGroup): static
    {
        $this->relatedGroup = $relatedGroup;

        return $this;
    }

    public function isUpcoming(\DateTimeImmutable $now): bool
    {
        return null !== $this->startDate && $this->startDate > $now;
    }

    public function isOngoing(\DateTimeImmutable $now): bool
    {
        if (null === $this->startDate || $this->startDate > $now) {
            return false;
        }

        if (null !== $this->endDate) {
            return $this->endDate >= $now;
        }

        $todayStart = $now->setTime(0, 0, 0);

        return $this->startDate >= $todayStart;
    }

    public function isPast(\DateTimeImmutable $now): bool
    {
        if (null === $this->startDate) {
            return false;
        }

        if (null !== $this->endDate) {
            return $this->endDate < $now;
        }

        $todayStart = $now->setTime(0, 0, 0);

        return $this->startDate < $todayStart;
    }

    public function validateDateRange(ExecutionContextInterface $context): void
    {
        if (null === $this->startDate) {
            return;
        }

        if (null !== $this->endDate && $this->endDate < $this->startDate) {
            $context->buildViolation('event.end_date.before_start')
                ->atPath('endDate')
                ->addViolation();
        }
    }

    public function getAdminLabel(): string
    {
        return '' !== $this->title
            ? $this->title
            : 'Événement #'.($this->id ?? '?');
    }
}
