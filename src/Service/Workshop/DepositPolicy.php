<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\Registration;
use App\Entity\Workshop;
use App\Entity\WorkshopSession;
use App\Enum\SessionStatus;
use App\Service\SiteSettingsProvider;
use Psr\Clock\ClockInterface;

/**
 * Règles de l'acompte des ateliers. Le site ne gère aucun paiement : la personne paie sur SumUp
 * (lien saisi sur l'atelier) et l'artisane coche « Acompte reçu » dans l'admin.
 * - acompte demandé une fois la date confirmée, à payer sous N jours (réglages) ;
 * - annulation remboursée jusqu'à N jours avant l'atelier (réglages), acompte conservé ensuite.
 */
final readonly class DepositPolicy
{
    public function __construct(
        private SiteSettingsProvider $settings,
        private ClockInterface $clock,
    ) {
    }

    public function isRequired(?Workshop $workshop): bool
    {
        return null !== $workshop && ($workshop->getDepositCents() ?? 0) > 0;
    }

    /** L'acompte est attendu : atelier avec acompte et date confirmée. */
    public function isExpected(Registration $registration): bool
    {
        $session = $registration->getSession();

        return null !== $session
            && SessionStatus::Confirmed === $session->getStatus()
            && $this->isRequired($session->getWorkshop());
    }

    public function paymentDays(): int
    {
        return $this->settings->get()->getDepositPaymentDays();
    }

    public function refundDaysBefore(): int
    {
        return $this->settings->get()->getRefundDaysBefore();
    }

    /** Date limite de paiement : N jours après la confirmation, jamais après le début de l'atelier. */
    public function paymentDeadline(WorkshopSession $session): ?\DateTimeImmutable
    {
        $confirmedAt = $session->getConfirmedAt();
        $startsAt = $session->getStartsAt();
        if (null === $confirmedAt || null === $startsAt) {
            return null;
        }
        $deadline = $confirmedAt->modify(\sprintf('+%d days', $this->paymentDays()))->setTime(23, 59);

        return min($deadline, $startsAt);
    }

    /** Acompte attendu, non reçu, et date limite dépassée. */
    public function isLate(Registration $registration): bool
    {
        if (!$this->isExpected($registration) || $registration->isDepositPaid()) {
            return false;
        }
        $deadline = $this->paymentDeadline($registration->getSession() ?? throw new \LogicException('Inscription sans date.'));

        return null !== $deadline && $deadline < $this->clock->now();
    }

    /** Dernier moment pour annuler avec remboursement de l'acompte. */
    public function refundLimit(WorkshopSession $session): ?\DateTimeImmutable
    {
        return $session->getStartsAt()?->modify(\sprintf('-%d days', $this->refundDaysBefore()));
    }

    /** Une annulation maintenant donnerait droit au remboursement de l'acompte. */
    public function isRefundableNow(WorkshopSession $session): bool
    {
        $limit = $this->refundLimit($session);

        return null !== $limit && $this->clock->now() <= $limit;
    }
}
