<?php

declare(strict_types=1);

namespace App\Service\Catalog;

/**
 * Source de catalogue. V1 : export CSV SumUp. L'API publique SumUp n'expose pas le catalogue :
 * une autre implémentation ne viendra que si cela change.
 */
interface ProductSourceInterface
{
    /**
     * @throws CatalogSourceException si la source est illisible ou mal formée
     */
    public function read(): CatalogData;

    /** Libellé lisible de la source (nom du fichier…), pour les messages. */
    public function getLabel(): string;
}
