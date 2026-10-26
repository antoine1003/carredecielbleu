<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WorkshopRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Atelier de vitrail (contenu), proposé à plusieurs dates (WorkshopSession).
 * Aucun paiement n'est géré par le site : le tarif est un simple texte d'information.
 */
#[ORM\Entity(repositoryClass: WorkshopRepository::class)]
#[UniqueEntity('slug')]
class Workshop
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    private ?string $title = null;

    #[ORM\Column(length: 160, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Lettres minuscules, chiffres et tirets uniquement.')]
    private ?string $slug = null;

    /** Phrase d'accroche affichée dans la liste des ateliers. */
    #[ORM\Column(length: 300, nullable: true)]
    #[Assert\Length(max: 300)]
    private ?string $summary = null;

    /** Description complète (HTML assaini à l'affichage). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** Durée en clair (« 3 heures », « une journée »). */
    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $duration = null;

    /** Tarif en clair, pour information (« 60 € par personne, matériel compris »). */
    #[ORM\Column(length: 160, nullable: true)]
    #[Assert\Length(max: 160)]
    private ?string $priceInfo = null;

    /** Lieu par défaut des dates. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $defaultLocation = null;

    /** Nombre de places par défaut des dates. */
    #[ORM\Column]
    #[Assert\Positive]
    private int $defaultCapacity = 6;

    #[ORM\Column]
    private bool $published = false;

    /** @var Collection<int, WorkshopSession> */
    #[ORM\OneToMany(targetEntity: WorkshopSession::class, mappedBy: 'workshop', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['startsAt' => 'ASC'])]
    private Collection $sessions;

    public function __construct()
    {
        $this->sessions = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->title;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): static
    {
        $this->summary = $summary;

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

    public function getDuration(): ?string
    {
        return $this->duration;
    }

    public function setDuration(?string $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function getPriceInfo(): ?string
    {
        return $this->priceInfo;
    }

    public function setPriceInfo(?string $priceInfo): static
    {
        $this->priceInfo = $priceInfo;

        return $this;
    }

    public function getDefaultLocation(): ?string
    {
        return $this->defaultLocation;
    }

    public function setDefaultLocation(?string $defaultLocation): static
    {
        $this->defaultLocation = $defaultLocation;

        return $this;
    }

    public function getDefaultCapacity(): int
    {
        return $this->defaultCapacity;
    }

    public function setDefaultCapacity(?int $defaultCapacity): static
    {
        $this->defaultCapacity = (int) $defaultCapacity;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): static
    {
        $this->published = $published;

        return $this;
    }

    /** @return Collection<int, WorkshopSession> */
    public function getSessions(): Collection
    {
        return $this->sessions;
    }

    public function addSession(WorkshopSession $session): static
    {
        if (!$this->sessions->contains($session)) {
            $this->sessions->add($session);
            $session->setWorkshop($this);
        }

        return $this;
    }

    public function removeSession(WorkshopSession $session): static
    {
        $this->sessions->removeElement($session);

        return $this;
    }
}
