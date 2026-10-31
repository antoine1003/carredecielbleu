<?php

declare(strict_types=1);

namespace App\Service\Workshop;

/**
 * Situation d'un atelier, du point de vue de l'artisane.
 */
enum WorkshopState: string
{
    /** Une date à venir est retenue : l'atelier aura lieu. */
    case Confirmed = 'confirmed';
    /** Des personnes sont inscrites, aucune date retenue : il faut choisir. */
    case ToDecide = 'to_decide';
    /** Des dates sont proposées, personne n'est encore inscrit. */
    case Waiting = 'waiting';
    /** Aucune date à venir. */
    case NoDates = 'no_dates';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Date retenue',
            self::ToDecide => 'Date à choisir',
            self::Waiting => 'En attente d\'inscriptions',
            self::NoDates => 'Aucune date à venir',
        };
    }

    /** Ordre sur l'accueil de l'admin : ce qui demande une action d'abord. */
    public function priority(): int
    {
        return match ($this) {
            self::ToDecide => 0,
            self::Confirmed => 1,
            self::Waiting => 2,
            self::NoDates => 3,
        };
    }

    /** Couleur du badge EasyAdmin. */
    public function color(): string
    {
        return match ($this) {
            self::Confirmed => 'success',
            self::ToDecide => 'warning',
            self::Waiting => 'info',
            self::NoDates => 'secondary',
        };
    }
}
