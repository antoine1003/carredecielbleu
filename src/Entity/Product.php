<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SoldOutBehavior;
use App\Enum\SyncStatus;
use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Produit de la boutique.
 *
 * Champs « SumUp » : écrits par l'import (nom, description de base, catégorie, SEO SumUp, variantes).
 * Champs « enrichis » : saisis dans l'admin, jamais écrasés par l'import.
 */
#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['sku'], name: 'idx_product_sku')]
#[UniqueEntity('slug')]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // ---------- Champs SumUp (mis à jour par l'import) ----------

    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $sumupItemId = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $sku = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    /** Description de base issue de SumUp (HTML assaini). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Category $category = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sumupSeoTitle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $sumupSeoDescription = null;

    /** @var Collection<int, ProductVariant> */
    #[ORM\OneToMany(targetEntity: ProductVariant::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    #[Assert\Valid]
    private Collection $variants;

    // ---------- Champs enrichis (jamais touchés par l'import) ----------

    #[ORM\Column(length: 160, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Lettres minuscules, chiffres et tirets uniquement.')]
    private ?string $slug = null;

    #[ORM\Column]
    private bool $visible = false;

    /** Mis en avant sur la page d'accueil. */
    #[ORM\Column]
    private bool $featured = false;

    /** Lien d'achat SumUp, saisi à la main. */
    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Url(protocols: ['https'], requireTld: true, message: 'Indiquez une adresse complète commençant par https://')]
    #[Assert\Length(max: 500)]
    private ?string $purchaseUrl = null;

    /** Texte d'histoire de la pièce (HTML assaini). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $story = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $technique = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $dimensions = null;

    /** Comportement quand le stock suivi est à zéro ; null = réglage général du site. */
    #[ORM\Column(length: 32, nullable: true, enumType: SoldOutBehavior::class)]
    private ?SoldOutBehavior $soldOutBehavior = null;

    /** @var Collection<int, ProductImage> */
    #[ORM\OneToMany(targetEntity: ProductImage::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $images;

    // ---------- Synchronisation ----------

    #[ORM\Column(length: 16, enumType: SyncStatus::class)]
    private SyncStatus $syncStatus = SyncStatus::Manual;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSyncedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->variants = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSumupItemId(): ?string
    {
        return $this->sumupItemId;
    }

    public function setSumupItemId(?string $sumupItemId): static
    {
        $this->sumupItemId = $sumupItemId;

        return $this;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): static
    {
        $this->sku = $sku;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getSumupSeoTitle(): ?string
    {
        return $this->sumupSeoTitle;
    }

    public function setSumupSeoTitle(?string $sumupSeoTitle): static
    {
        $this->sumupSeoTitle = $sumupSeoTitle;

        return $this;
    }

    public function getSumupSeoDescription(): ?string
    {
        return $this->sumupSeoDescription;
    }

    public function setSumupSeoDescription(?string $sumupSeoDescription): static
    {
        $this->sumupSeoDescription = $sumupSeoDescription;

        return $this;
    }

    /** @return Collection<int, ProductVariant> */
    public function getVariants(): Collection
    {
        return $this->variants;
    }

    public function addVariant(ProductVariant $variant): static
    {
        if (!$this->variants->contains($variant)) {
            $this->variants->add($variant);
            $variant->setProduct($this);
        }

        return $this;
    }

    public function removeVariant(ProductVariant $variant): static
    {
        $this->variants->removeElement($variant);

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

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function setFeatured(bool $featured): static
    {
        $this->featured = $featured;

        return $this;
    }

    public function getPurchaseUrl(): ?string
    {
        return $this->purchaseUrl;
    }

    public function setPurchaseUrl(?string $purchaseUrl): static
    {
        $this->purchaseUrl = $purchaseUrl;

        return $this;
    }

    public function getStory(): ?string
    {
        return $this->story;
    }

    public function setStory(?string $story): static
    {
        $this->story = $story;

        return $this;
    }

    public function getTechnique(): ?string
    {
        return $this->technique;
    }

    public function setTechnique(?string $technique): static
    {
        $this->technique = $technique;

        return $this;
    }

    public function getDimensions(): ?string
    {
        return $this->dimensions;
    }

    public function setDimensions(?string $dimensions): static
    {
        $this->dimensions = $dimensions;

        return $this;
    }

    public function getSoldOutBehavior(): ?SoldOutBehavior
    {
        return $this->soldOutBehavior;
    }

    public function setSoldOutBehavior(?SoldOutBehavior $soldOutBehavior): static
    {
        $this->soldOutBehavior = $soldOutBehavior;

        return $this;
    }

    /** @return Collection<int, ProductImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(ProductImage $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setProduct($this);
        }

        return $this;
    }

    public function removeImage(ProductImage $image): static
    {
        $this->images->removeElement($image);

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

    public function getLastSyncedAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    public function setLastSyncedAt(?\DateTimeImmutable $lastSyncedAt): static
    {
        $this->lastSyncedAt = $lastSyncedAt;

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
}
