<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductImageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Photo d'un produit, uploadée dans l'admin (redimensionnement WebP : phase admin).
 * Les fichiers vivent dans public/uploads/products/ ; seul le nom de base est stocké.
 */
#[ORM\Entity(repositoryClass: ProductImageRepository::class)]
class ProductImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    /** Nom de base aléatoire, sans extension ni taille (ex. « 3f9c…a1 »). */
    #[ORM\Column(length: 64)]
    private ?string $filename = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Décrivez la photo pour les personnes malvoyantes et le référencement.')]
    #[Assert\Length(max: 255)]
    private ?string $alt = null;

    /** Dimensions de l'original redimensionné (pour width/height et le ratio). */
    #[ORM\Column]
    private int $width = 0;

    #[ORM\Column]
    private int $height = 0;

    /** @var list<int> Largeurs générées en WebP, ex. [480, 960, 1600] */
    #[ORM\Column(type: Types::JSON)]
    private array $widths = [];

    #[ORM\Column]
    private int $position = 0;

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

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(?string $alt): static
    {
        $this->alt = $alt;

        return $this;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function setWidth(int $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function setHeight(int $height): static
    {
        $this->height = $height;

        return $this;
    }

    /** @return list<int> */
    public function getWidths(): array
    {
        return $this->widths;
    }

    /** @param list<int> $widths */
    public function setWidths(array $widths): static
    {
        $this->widths = $widths;

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
}
