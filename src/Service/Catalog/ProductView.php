<?php

declare(strict_types=1);

namespace App\Service\Catalog;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Enum\Availability;

/**
 * Produit prêt à afficher côté public : disponibilité et prix calculés.
 */
final readonly class ProductView
{
    /**
     * @param list<array{variant: ProductVariant, availability: Availability}> $variants variantes affichables
     */
    public function __construct(
        public Product $product,
        public Availability $availability,
        public ?int $priceCents,
        public bool $fromPrice,
        public array $variants,
    ) {
    }

    public function isVisible(): bool
    {
        return Availability::Hidden !== $this->availability;
    }

    /** Le bouton « Acheter sur SumUp » s'affiche (sinon « Me contacter »). */
    public function canBuy(): bool
    {
        return null !== $this->product->getPurchaseUrl() && $this->availability->isPurchasable();
    }

    /** Prix en euros pour les composants Twig (null = prix sur demande). */
    public function price(): ?float
    {
        return null === $this->priceCents ? null : $this->priceCents / 100;
    }
}
