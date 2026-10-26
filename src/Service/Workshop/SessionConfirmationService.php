<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\Registration;
use App\Entity\WorkshopSession;
use App\Enum\SessionStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Confirmation par l'artisane de la date retenue pour un atelier :
 * - la date passe en « confirmée » ;
 * - ses inscrits reçoivent un email de confirmation ;
 * - les inscrits des autres dates (et seulement d'elles) reçoivent un email d'information ;
 * - en option, les autres dates à venir sont annulées.
 */
final readonly class SessionConfirmationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WorkshopMailer $mailer,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws \DomainException si la date ne peut pas être confirmée (message pour l'admin)
     */
    public function confirm(WorkshopSession $session, bool $cancelOtherSessions): ConfirmationResult
    {
        $workshop = $session->getWorkshop() ?? throw new \LogicException('Date sans atelier.');
        if (SessionStatus::Confirmed === $session->getStatus()) {
            throw new \DomainException('Cette date est déjà confirmée : les emails ont déjà été envoyés.');
        }
        if (SessionStatus::Cancelled === $session->getStatus()) {
            throw new \DomainException('Cette date est annulée : rouvrez-la avant de la confirmer.');
        }
        $now = $this->clock->now();
        if (null === $session->getStartsAt() || $session->getStartsAt() <= $now) {
            throw new \DomainException('Cette date est passée.');
        }

        $session->setStatus(SessionStatus::Confirmed)->setConfirmedAt($now);

        $confirmedEmails = [];
        foreach ($session->getRegistrations() as $registration) {
            $confirmedEmails[(string) $registration->getEmail()] = true;
        }

        // Inscrits des autres dates à venir non annulées, une seule fois par adresse.
        /** @var array<string, Registration> $others */
        $others = [];
        $cancelledSessions = 0;
        foreach ($workshop->getSessions() as $other) {
            if ($other === $session || SessionStatus::Cancelled === $other->getStatus() || $other->getStartsAt() <= $now) {
                continue;
            }
            foreach ($other->getRegistrations() as $registration) {
                $email = (string) $registration->getEmail();
                if (!isset($confirmedEmails[$email])) {
                    $others[$email] ??= $registration;
                }
            }
            if ($cancelOtherSessions && SessionStatus::Confirmed !== $other->getStatus()) {
                $other->setStatus(SessionStatus::Cancelled);
                ++$cancelledSessions;
            }
        }

        $this->entityManager->flush();

        $sent = $failed = 0;
        foreach ($session->getRegistrations() as $registration) {
            $this->mailer->sendSessionConfirmed($registration) ? ++$sent : ++$failed;
        }
        foreach ($others as $registration) {
            $this->mailer->sendOtherSessionChosen($registration, $session, $cancelOtherSessions) ? ++$sent : ++$failed;
        }

        return new ConfirmationResult(
            confirmedCount: $session->getRegistrations()->count(),
            informedCount: \count($others),
            cancelledSessions: $cancelledSessions,
            emailsSent: $sent,
            emailsFailed: $failed,
        );
    }
}
