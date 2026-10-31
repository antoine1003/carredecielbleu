<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SyncStatus;
use App\Repository\ProductVariantRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Déclinaison d'un produit (prix et stock propres).
 * Un produit simple a une seule variante, sans nom.
 */
#[ORM\Entity(repositoryClass: ProductVariantRepository::class)]
class ProductVariant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'variants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $sumupVariantId = null;

    /** Nom de la déclinaison (« épis bleu ») ; null pour un produit simple. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    /** Prix en centimes ; null ou 0 = prix sur demande. */
    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $priceCents = null;

    #[ORM\Column]
    private bool $trackInventory = true;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $stock = 0;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column(length: 16, enumType: SyncStatus::class)]
    private SyncStatus $syncStatus = SyncStatus::Manual;

    public function __toString(): string
    {
        return $this->name ?? (string) $this->product?->getName();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getSumupVariantId(): ?string
    {
        return $this->sumupVariantId;
    }

    public function setSumupVariantId(?string $sumupVariantId): static
    {
        $this->sumupVariantId = $sumupVariantId;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getPriceCents(): ?int
    {
        return $this->priceCents;
    }

    public function setPriceCents(?int $priceCents): static
    {
        $this->priceCents = $priceCents;

        return $this;
    }

    public function isTrackInventory(): bool
    {
        return $this->trackInventory;
    }

    public function setTrackInventory(bool $trackInventory): static
    {
        $this->trackInventory = $trackInventory;

        return $this;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function setStock(int $stock): static
    {
        $this->stock = $stock;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getSyncStatus(): SyncStatus
    {
        return $this->syncStatus;
    }

    public function setSyncStatus(SyncStatus $syncStatus): static
    {
        $this->syncStatus = $syncStatus;

        return $this;
    }
}
