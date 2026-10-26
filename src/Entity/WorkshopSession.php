<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SessionStatus;
use App\Repository\WorkshopSessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Date proposée pour un atelier. Les personnes s'inscrivent à une ou plusieurs dates ;
 * l'artisane confirme ensuite la date retenue.
 */
#[ORM\Entity(repositoryClass: WorkshopSessionRepository::class)]
#[ORM\Index(columns: ['starts_at'], name: 'idx_session_starts_at')]
class WorkshopSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Workshop $workshop = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Indiquez la date et l\'heure de début.')]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(nullable: true)]
    #[Assert\GreaterThan(propertyPath: 'startsAt', message: 'La fin doit être après le début.')]
    private ?\DateTimeImmutable $endsAt = null;

    /** Lieu de cette date ; vide = lieu par défaut de l'atelier. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $location = null;

    /** Nombre de places ; vide = capacité par défaut de l'atelier. */
    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $capacity = null;

    #[ORM\Column(length: 16, enumType: SessionStatus::class)]
    private SessionStatus $status = SessionStatus::Open;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    /** @var Collection<int, Registration> */
    #[ORM\OneToMany(targetEntity: Registration::class, mappedBy: 'session', cascade: ['remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $registrations;

    public function __construct()
    {
        $this->registrations = new ArrayCollection();
    }

    public function __toString(): string
    {
        $date = null === $this->startsAt ? '' : ' — '.$this->startsAt->format('d/m/Y H:i');

        return (string) $this->workshop?->getTitle().$date;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorkshop(): ?Workshop
    {
        return $this->workshop;
    }

    public function setWorkshop(?Workshop $workshop): static
    {
        $this->workshop = $workshop;

        return $this;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(?\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
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

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    /** Lieu effectif (celui de la date, sinon celui de l'atelier). */
    public function getEffectiveLocation(): ?string
    {
        return $this->location ?? $this->workshop?->getDefaultLocation();
    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function setCapacity(?int $capacity): static
    {
        $this->capacity = $capacity;

        return $this;
    }

    /** Capacité effective (celle de la date, sinon celle de l'atelier). */
    public function getEffectiveCapacity(): int
    {
        return $this->capacity ?? $this->workshop?->getDefaultCapacity() ?? 0;
    }

    public function getStatus(): SessionStatus
    {
        return $this->status;
    }

    public function setStatus(SessionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function setConfirmedAt(?\DateTimeImmutable $confirmedAt): static
    {
        $this->confirmedAt = $confirmedAt;

        return $this;
    }

    /** @return Collection<int, Registration> */
    public function getRegistrations(): Collection
    {
        return $this->registrations;
    }

    public function addRegistration(Registration $registration): static
    {
        if (!$this->registrations->contains($registration)) {
            $this->registrations->add($registration);
            $registration->setSession($this);
        }

        return $this;
    }

    public function removeRegistration(Registration $registration): static
    {
        $this->registrations->removeElement($registration);

        return $this;
    }
}
