<?php

declare(strict_types=1);

namespace App\Service\Workshop;

final readonly class ConfirmationResult
{
    public function __construct(
        public int $confirmedCount,
        public int $informedCount,
        public int $cancelledSessions,
        /** Inscriptions des personnes confirmées à d'autres dates, retirées. */
        public int $removedRegistrations,
        public int $emailsSent,
        public int $emailsFailed,
    ) {
    }

    public function summary(): string
    {
        $text = \sprintf(
            'Date confirmée. %d personne(s) inscrite(s) à cette date prévenue(s), %d personne(s) inscrite(s) à d\'autres dates informée(s)',
            $this->confirmedCount,
            $this->informedCount,
        );
        if ($this->cancelledSessions > 0) {
            $text .= \sprintf(', %d autre(s) date(s) annulée(s)', $this->cancelledSessions);
        }
        if ($this->removedRegistrations > 0) {
            $text .= \sprintf(', %d inscription(s) des participants à d\'autres dates retirée(s)', $this->removedRegistrations);
        }

        return $text.'.';
    }
}
