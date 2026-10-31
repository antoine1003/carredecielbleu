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
        /** Date à venir, non encore retenue (ouverte, complète ou annulée) : on peut la retenir. */
        public bool $canConfirm,
        /** Inscrits d'autres dates qui recevraient l'email d'information si on retenait celle-ci. */
        public int $othersToNotify,
        /** Acomptes reçus parmi les inscrits de cette date. */
        public int $depositsPaid = 0,
        /** Inscriptions de ces personnes à d'autres dates, retirées si l'on retient celle-ci. */
        public int $registrationsToRemove = 0,
        /** Personnes concernées par ce retrait. */
        public int $peopleWithOtherDates = 0,
    ) {
    }
}
