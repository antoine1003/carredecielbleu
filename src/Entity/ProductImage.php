<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductImageRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Photo d'un produit (fichiers dans public/uploads/products/).
 */
#[ORM\Entity(repositoryClass: ProductImageRepository::class)]
class ProductImage implements ResponsiveImage
{
    use ResponsiveImageTrait;

    #[ORM\ManyToOne(inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    public static function uploadFolder(): string
    {
        return 'products';
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
}
