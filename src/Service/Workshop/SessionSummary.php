<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\WorkshopSession;

/**
 * Une date d'atelier vue depuis l'admin : remplissage et effet d'une confirmation.
 */
final readonly class SessionSummary
{
    public function __construct(
        public WorkshopSession $session,
        public int $registered,
        public int $capacity,
        /** Remplissage de 0 à 100, pour la jauge. */
        public int $fillPercent,
        /** Date à venir, ouverte ou complète : on peut la retenir. */
        public bool $canConfirm,
        /** Inscrits d'autres dates qui recevraient l'email d'information si on retenait celle-ci. */
        public int $othersToNotify,
        /** Acomptes reçus parmi les inscrits de cette date. */
        public int $depositsPaid = 0,
    ) {
    }
}
