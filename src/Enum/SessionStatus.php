<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Statut d'une date d'atelier.
 */
enum SessionStatus: string implements TranslatableInterface
{
    /** Inscriptions ouvertes (sondage de disponibilités). */
    case Open = 'open';
    /** Date retenue par l'artisane : l'atelier a lieu. */
    case Confirmed = 'confirmed';
    /** Plus de place : les inscriptions sont fermées. */
    case Full = 'full';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Ouverte',
            self::Confirmed => 'Confirmée',
            self::Full => 'Complète',
            self::Cancelled => 'Annulée',
        };
    }

    public function acceptsRegistrations(): bool
    {
        return self::Open === $this || self::Confirmed === $this;
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans($this->label(), locale: $locale);
    }
}
