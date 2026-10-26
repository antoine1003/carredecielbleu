<?php

declare(strict_types=1);

namespace App\Service\Catalog;

final readonly class CatalogVariant
{
    public function __construct(
        public ?string $variantId,
        public ?string $name,
        public ?int $priceCents,
        public bool $trackInventory,
        public int $stock,
    ) {
    }
}
