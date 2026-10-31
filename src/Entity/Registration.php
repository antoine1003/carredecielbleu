<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RegistrationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Inscription d'une personne à UNE date d'atelier. Une personne qui coche plusieurs dates
 * a plusieurs inscriptions, reliées par le même identifiant de demande (submissionId).
 *
 * Données personnelles : minimum nécessaire, jamais affichées en public sauf prénom + initiale
 * avec accord explicite (showNamePublicly). Supprimées à l'annulation et purgées après l'atelier
 * (voir app:registrations:purge).
 */
#[ORM\Entity(repositoryClass: RegistrationRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_registration_session_email', columns: ['session_id', 'email'])]
class Registration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'registrations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?WorkshopSession $session = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    private ?string $firstName = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    private ?string $lastName = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Length(max: 30)]
    private ?string $phone = null;

    /** Accord explicite pour afficher « Prénom I. » dans la liste publique. */
    #[ORM\Column]
    private bool $showNamePublicly = false;

    /** Jeton d'annulation : 32 octets aléatoires en hexadécimal, non devinable. */
    #[ORM\Column(length: 64, unique: true)]
    private string $cancelToken;

    /** Identifiant commun aux inscriptions envoyées dans le même formulaire. */
    #[ORM\Column(length: 32)]
    private string $submissionId;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** Acceptation des conditions d'annulation (ateliers avec acompte), preuve horodatée. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $termsAcceptedAt = null;

    /** Acompte reçu (coché par l'artisane quand le paiement SumUp est arrivé). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $depositPaidAt = null;

    /** Dernière relance envoyée pour l'acompte. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $depositReminderSentAt = null;

    public function __construct(?string $submissionId = null)
    {
        $this->cancelToken = bin2hex(random_bytes(32));
        $this->submissionId = $submissionId ?? bin2hex(random_bytes(16));
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return trim($this->firstName.' '.$this->lastName);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSession(): ?WorkshopSession
    {
        return $this->session;
    }

    public function setSession(?WorkshopSession $session): static
    {
        $this->session = $session;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = null === $email ? null : mb_strtolower(trim($email));

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function isShowNamePublicly(): bool
    {
        return $this->showNamePublicly;
    }

    public function setShowNamePublicly(bool $showNamePublicly): static
    {
        $this->showNamePublicly = $showNamePublicly;

        return $this;
    }

    /** Nom affichable en public : « Prénom I. » (uniquement si accord). */
    public function getPublicName(): string
    {
        $initial = mb_strtoupper(mb_substr(trim((string) $this->lastName), 0, 1));

        return trim((string) $this->firstName).('' === $initial ? '' : ' '.$initial.'.');
    }

    public function getCancelToken(): string
    {
        return $this->cancelToken;
    }

    public function getSubmissionId(): string
    {
        return $this->submissionId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getTermsAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->termsAcceptedAt;
    }

    public function setTermsAcceptedAt(?\DateTimeImmutable $termsAcceptedAt): static
    {
        $this->termsAcceptedAt = $termsAcceptedAt;

        return $this;
    }

    public function getDepositPaidAt(): ?\DateTimeImmutable
    {
        return $this->depositPaidAt;
    }

    public function setDepositPaidAt(?\DateTimeImmutable $depositPaidAt): static
    {
        $this->depositPaidAt = $depositPaidAt;

        return $this;
    }

    public function isDepositPaid(): bool
    {
        return null !== $this->depositPaidAt;
    }

    public function getDepositReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->depositReminderSentAt;
    }

    public function setDepositReminderSentAt(?\DateTimeImmutable $depositReminderSentAt): static
    {
        $this->depositReminderSentAt = $depositReminderSentAt;

        return $this;
    }
}
