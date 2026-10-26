<?php

declare(strict_types=1);

namespace App\Service\Catalog;

/**
 * Contenu lu dans une source de catalogue.
 */
final readonly class CatalogData
{
    /**
     * @param list<CatalogItem> $items
     * @param list<string>      $warnings lignes ignorées ou suspectes, en français
     */
    public function __construct(
        public array $items,
        public array $warnings = [],
    ) {
    }
}
