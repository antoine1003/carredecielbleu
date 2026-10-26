<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * État de synchronisation d'un produit ou d'une variante avec le catalogue SumUp.
 */
enum SyncStatus: string implements TranslatableInterface
{
    /** Présent dans le dernier import. */
    case Synced = 'synced';
    /** Absent du dernier import : conservé, jamais supprimé automatiquement. */
    case NotSynced = 'not_synced';
    /** Créé à la main dans l'admin, ignoré par l'import. */
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Synced => 'Synchronisé',
            self::NotSynced => 'Non synchronisé',
            self::Manual => 'Saisi à la main',
        };
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->label(), locale: $locale);
    }
}
