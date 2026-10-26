<?php

declare(strict_types=1);

namespace App\Service\Workshop;

final readonly class ConfirmationResult
{
    public function __construct(
        public int $confirmedCount,
        public int $informedCount,
        public int $cancelledSessions,
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

        return $text.'.';
    }
}
