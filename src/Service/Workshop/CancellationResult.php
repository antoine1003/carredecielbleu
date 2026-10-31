<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\WorkshopSession;

/**
 * Résultat d'une annulation par la personne inscrite.
 */
final readonly class CancellationResult
{
    public function __construct(
        public WorkshopSession $session,
        /** L'acompte avait été reçu. */
        public bool $depositPaid,
        /** Acompte reçu et annulation dans le délai : l'artisane doit le rembourser. */
        public bool $refundDue,
    ) {
    }
}
