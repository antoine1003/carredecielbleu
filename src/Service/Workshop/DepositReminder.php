<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\WorkshopSession;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Relance par email des personnes d'une date confirmée dont l'acompte n'est pas arrivé.
 */
final readonly class DepositReminder
{
    public function __construct(
        private DepositPolicy $policy,
        private WorkshopMailer $mailer,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{sent: int, failed: int}
     */
    public function remindUnpaid(WorkshopSession $session): array
    {
        $sent = $failed = 0;
        foreach ($session->getRegistrations() as $registration) {
            if (!$this->policy->isExpected($registration) || $registration->isDepositPaid()) {
                continue;
            }
            if ($this->mailer->sendDepositReminder($registration)) {
                $registration->setDepositReminderSentAt($this->clock->now());
                ++$sent;
            } else {
                ++$failed;
            }
        }
        $this->entityManager->flush();

        return ['sent' => $sent, 'failed' => $failed];
    }
}
