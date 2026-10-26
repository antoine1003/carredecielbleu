<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Disponibilité publique d'un produit ou d'une variante.
 */
enum Availability: string
{
    case Available = 'available';
    case MadeToOrder = 'made_to_order';
    case SoldOut = 'sold_out';
    case Hidden = 'hidden';

    public function isPurchasable(): bool
    {
        return self::Available === $this || self::MadeToOrder === $this;
    }

    /** Valeur schema.org pour le JSON-LD (phase SEO). */
    public function schemaOrg(): string
    {
        return match ($this) {
            self::Available => 'https://schema.org/InStock',
            self::MadeToOrder => 'https://schema.org/PreOrder',
            self::SoldOut, self::Hidden => 'https://schema.org/OutOfStock',
        };
    }
}
