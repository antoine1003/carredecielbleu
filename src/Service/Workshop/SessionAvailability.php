<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\WorkshopSession;
use Psr\Clock\ClockInterface;

/**
 * Règles d'ouverture des inscriptions et d'affichage public d'une date d'atelier.
 */
final readonly class SessionAvailability
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    public function registeredCount(WorkshopSession $session): int
    {
        return $session->getRegistrations()->count();
    }

    public function remainingPlaces(WorkshopSession $session): int
    {
        return max(0, $session->getEffectiveCapacity() - $this->registeredCount($session));
    }

    /** Inscription possible : statut ouvert ou confirmé, date à venir, places restantes. */
    public function acceptsRegistrations(WorkshopSession $session): bool
    {
        return $session->getStatus()->acceptsRegistrations()
            && null !== $session->getStartsAt()
            && $session->getStartsAt() > $this->clock->now()
            && $this->remainingPlaces($session) > 0;
    }

    /**
     * Noms affichables en public : « Prénom I. », uniquement pour les personnes qui ont accepté.
     *
     * @return list<string>
     */
    public function publicNames(WorkshopSession $session): array
    {
        $names = [];
        foreach ($session->getRegistrations() as $registration) {
            if ($registration->isShowNamePublicly()) {
                $names[] = $registration->getPublicName();
            }
        }

        return $names;
    }
}
