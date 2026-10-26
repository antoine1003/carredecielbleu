<?php

declare(strict_types=1);

namespace App\Service\Catalog;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Enum\Availability;
use App\Enum\SoldOutBehavior;
use App\Enum\SyncStatus;
use App\Service\SiteSettingsProvider;

/**
 * Règles de disponibilité publique (voir CLAUDE.md) :
 * - stock non suivi → « Sur commande » ;
 * - stock suivi > 0 → « En stock » ;
 * - stock suivi à 0 → comportement « épuisé » du produit, sinon celui du site ;
 * - produit ou variante « non synchronisé » (retiré de SumUp) → non affiché ;
 * - prix à 0 € → prix sur demande.
 */
final readonly class ProductAvailability
{
    public function __construct(
        private SiteSettingsProvider $settings,
    ) {
    }

    public function view(Product $product): ProductView
    {
        if (!$product->isVisible() || SyncStatus::NotSynced === $product->getSyncStatus()) {
            return new ProductView($product, Availability::Hidden, null, false, []);
        }

        $variants = [];
        foreach ($product->getVariants() as $variant) {
            if (SyncStatus::NotSynced === $variant->getSyncStatus()) {
                continue;
            }
            $variants[] = ['variant' => $variant, 'availability' => $this->forVariant($variant)];
        }

        $availability = $this->aggregate($product, array_column($variants, 'availability'));

        $prices = [];
        foreach ($variants as ['variant' => $variant, 'availability' => $variantAvailability]) {
            $price = $variant->getPriceCents();
            if (null !== $price && $price > 0 && (Availability::SoldOut !== $variantAvailability || Availability::SoldOut === $availability)) {
                $prices[] = $price;
            }
        }
        $prices = array_values(array_unique($prices));

        return new ProductView(
            $product,
            $availability,
            [] === $prices ? null : min($prices),
            \count($prices) > 1,
            $variants,
        );
    }

    /**
     * @param iterable<Product> $products
     *
     * @return list<ProductView> uniquement les produits affichables
     */
    public function visibleViews(iterable $products): array
    {
        $views = [];
        foreach ($products as $product) {
            $view = $this->view($product);
            if ($view->isVisible()) {
                $views[] = $view;
            }
        }

        return $views;
    }

    public function forVariant(ProductVariant $variant): Availability
    {
        return match (true) {
            !$variant->isTrackInventory() => Availability::MadeToOrder,
            $variant->getStock() > 0 => Availability::Available,
            default => Availability::SoldOut,
        };
    }

    /**
     * @param list<Availability> $availabilities
     */
    private function aggregate(Product $product, array $availabilities): Availability
    {
        if ([] === $availabilities) {
            // Produit saisi à la main sans variante : présenté sur commande.
            return SyncStatus::Manual === $product->getSyncStatus() ? Availability::MadeToOrder : Availability::Hidden;
        }
        if (\in_array(Availability::Available, $availabilities, true)) {
            return Availability::Available;
        }
        if (\in_array(Availability::MadeToOrder, $availabilities, true)) {
            return Availability::MadeToOrder;
        }

        return match ($product->getSoldOutBehavior() ?? $this->settings->get()->getSoldOutBehavior()) {
            SoldOutBehavior::Hide => Availability::Hidden,
            SoldOutBehavior::ShowSoldOut => Availability::SoldOut,
            SoldOutBehavior::ShowMadeToOrder => Availability::MadeToOrder,
        };
    }
}
