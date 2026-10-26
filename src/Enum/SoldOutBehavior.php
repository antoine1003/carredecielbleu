<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Affichage d'un produit dont le stock suivi est à zéro.
 */
enum SoldOutBehavior: string implements TranslatableInterface
{
    case Hide = 'hide';
    case ShowSoldOut = 'show_sold_out';
    case ShowMadeToOrder = 'show_made_to_order';

    public function label(): string
    {
        return match ($this) {
            self::Hide => 'Masquer le produit',
            self::ShowSoldOut => 'Afficher « Épuisé »',
            self::ShowMadeToOrder => 'Afficher « Sur commande »',
        };
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->label(), locale: $locale);
    }
}
