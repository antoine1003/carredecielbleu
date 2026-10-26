<?php

declare(strict_types=1);

namespace App\Service\Catalog;

/**
 * Produit tel que décrit par une source de catalogue (CSV SumUp aujourd'hui, API demain).
 * Valeurs déjà normalisées : textes « trimés », description assainie, prix en centimes.
 */
final readonly class CatalogItem
{
    /**
     * @param list<CatalogVariant> $variants au moins une variante (sans nom pour un produit simple)
     */
    public function __construct(
        public ?string $itemId,
        public ?string $sku,
        public string $name,
        public ?string $description,
        public ?string $categoryName,
        public bool $displayedInOnlineStore,
        public ?string $seoTitle,
        public ?string $seoDescription,
        public array $variants,
        public int $line,
    ) {
    }
}
