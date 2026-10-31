<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\Workshop;

/**
 * Où en est un atelier (voir WorkshopOverview).
 */
final readonly class WorkshopSummary
{
    /**
     * @param list<SessionSummary> $upcoming dates à venir, de la plus proche à la plus lointaine
     * @param list<SessionSummary> $past     dates passées, de la plus récente à la plus ancienne
     */
    public function __construct(
        public Workshop $workshop,
        public WorkshopState $state,
        public ?SessionSummary $confirmed,
        public ?SessionSummary $mostRequested,
        public array $upcoming,
        public array $past,
        /** Personnes différentes inscrites à au moins une date à venir. */
        public int $peopleCount,
        public ?\DateTimeImmutable $nextDate,
    ) {
    }
}
