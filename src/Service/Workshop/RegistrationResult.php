<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\Registration;
use App\Entity\WorkshopSession;

final readonly class RegistrationResult
{
    /**
     * @param list<Registration>    $created     nouvelles inscriptions
     * @param list<Registration>    $existing    dates auxquelles la personne était déjà inscrite
     * @param list<WorkshopSession> $unavailable dates complètes ou fermées entre-temps
     */
    public function __construct(
        public array $created,
        public array $existing,
        public array $unavailable,
        public bool $emailSent,
    ) {
    }
}
