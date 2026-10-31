<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\Registration;
use App\Entity\Workshop;
use App\Entity\WorkshopSession;
use App\Enum\SessionStatus;
use Psr\Clock\ClockInterface;

/**
 * Vue d'ensemble d'un atelier pour l'admin : où en est-on (date retenue ? à décider ?),
 * combien de personnes, quelle date est la plus demandée, et qui serait prévenu
 * si l'on retenait telle date.
 */
final readonly class WorkshopOverview
{
    public function __construct(
        private SessionAvailability $availability,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Ateliers qui ont des dates à venir, dans l'ordre où l'artisane doit s'en occuper :
     * date à choisir, puis date retenue (la plus proche d'abord), puis en attente d'inscriptions.
     *
     * @param iterable<Workshop> $workshops
     *
     * @return list<WorkshopSummary>
     */
    public function withUpcomingDates(iterable $workshops): array
    {
        $summaries = [];
        foreach ($workshops as $workshop) {
            $summary = $this->of($workshop);
            if (WorkshopState::NoDates !== $summary->state) {
                $summaries[] = $summary;
            }
        }
        usort($summaries, static fn (WorkshopSummary $a, WorkshopSummary $b): int => [$a->state->priority(), $a->confirmed?->session->getStartsAt() ?? $a->nextDate]
            <=> [$b->state->priority(), $b->confirmed?->session->getStartsAt() ?? $b->nextDate]);

        return $summaries;
    }

    public function of(Workshop $workshop): WorkshopSummary
    {
        $now = $this->clock->now();
        $upcoming = $past = [];
        foreach ($workshop->getSessions() as $session) {
            if (null !== $session->getStartsAt() && $session->getStartsAt() > $now) {
                $upcoming[] = $session;
            } else {
                $past[] = $session;
            }
        }
        usort($upcoming, static fn (WorkshopSession $a, WorkshopSession $b): int => $a->getStartsAt() <=> $b->getStartsAt());
        usort($past, static fn (WorkshopSession $a, WorkshopSession $b): int => $b->getStartsAt() <=> $a->getStartsAt());

        // Adresses des inscrits par date à venir non annulée (pour compter les personnes et les emails).
        $emailsBySession = [];
        foreach ($upcoming as $session) {
            if (SessionStatus::Cancelled !== $session->getStatus()) {
                $emailsBySession[(int) $session->getId()] = $this->emails($session);
            }
        }
        $people = [] === $emailsBySession ? [] : array_unique(array_merge(...array_values($emailsBySession)));

        $confirmedList = [];
        $upcomingSummaries = [];
        foreach ($upcoming as $session) {
            $summary = $this->summarize($session, $emailsBySession, true);
            $upcomingSummaries[] = $summary;
            if (SessionStatus::Confirmed === $session->getStatus()) {
                $confirmedList[] = $summary;
            }
        }
        $confirmed = $confirmedList[0] ?? null;

        // Personnes inscrites seulement à des dates pas encore retenues (de quoi faire un autre groupe).
        $waiting = [];
        foreach ($upcoming as $session) {
            if (\in_array($session->getStatus(), [SessionStatus::Open, SessionStatus::Full], true)) {
                $waiting = array_merge($waiting, $emailsBySession[(int) $session->getId()] ?? []);
            }
        }

        // Date la plus demandée parmi celles qu'on peut encore retenir (au moins une personne).
        $mostRequested = null;
        if (null === $confirmed) {
            foreach ($upcomingSummaries as $summary) {
                if ($summary->canConfirm && $summary->registered > 0 && $summary->registered > ($mostRequested->registered ?? 0)) {
                    $mostRequested = $summary;
                }
            }
        }

        $open = array_filter($upcomingSummaries, static fn (SessionSummary $s): bool => SessionStatus::Cancelled !== $s->session->getStatus());
        $state = match (true) {
            null !== $confirmed => WorkshopState::Confirmed,
            [] === $open => WorkshopState::NoDates,
            [] === $people => WorkshopState::Waiting,
            default => WorkshopState::ToDecide,
        };

        return new WorkshopSummary(
            workshop: $workshop,
            state: $state,
            confirmed: $confirmed,
            confirmedList: $confirmedList,
            waitingElsewhere: \count(array_unique($waiting)),
            mostRequested: $mostRequested,
            upcoming: $upcomingSummaries,
            past: array_map(fn (WorkshopSession $s): SessionSummary => $this->summarize($s, [], false), $past),
            peopleCount: \count($people),
            nextDate: ([] === $open ? null : reset($open))?->session->getStartsAt(),
        );
    }

    /**
     * @param array<int, list<string>> $emailsBySession
     */
    private function summarize(WorkshopSession $session, array $emailsBySession, bool $isUpcoming): SessionSummary
    {
        $registered = $this->availability->registeredCount($session);
        $capacity = $session->getEffectiveCapacity();
        $canConfirm = $isUpcoming && SessionStatus::Confirmed !== $session->getStatus();

        // Personnes des autres dates (ni annulées ni déjà confirmées) qui recevraient l'email
        // d'information si l'on retenait celle-ci.
        $mine = $emailsBySession[(int) $session->getId()] ?? $this->emails($session);
        $others = [];
        foreach ($session->getWorkshop()?->getSessions() ?? [] as $other) {
            if ($other !== $session && SessionStatus::Confirmed !== $other->getStatus() && isset($emailsBySession[(int) $other->getId()])) {
                $others = array_merge($others, $emailsBySession[(int) $other->getId()]);
            }
        }

        return new SessionSummary(
            session: $session,
            registered: $registered,
            capacity: $capacity,
            fillPercent: $capacity > 0 ? min(100, (int) round($registered * 100 / $capacity)) : 0,
            canConfirm: $canConfirm,
            othersToNotify: \count(array_diff(array_unique($others), $mine)),
            depositsPaid: \count(array_filter($session->getRegistrations()->toArray(), static fn (Registration $r): bool => $r->isDepositPaid())),
            registrationsToRemove: $canConfirm ? array_sum($removal = $this->otherDatesOf($session, $mine)) : 0,
            peopleWithOtherDates: $canConfirm ? \count($removal) : 0,
        );
    }

    /**
     * Inscriptions des personnes de cette date à d'autres dates à venir non confirmées
     * (retirées si l'on retient cette date, voir SessionConfirmationService).
     *
     * @param list<string> $emails adresses des inscrits de cette date
     *
     * @return array<string, int> nombre d'inscriptions retirées par adresse
     */
    private function otherDatesOf(WorkshopSession $session, array $emails): array
    {
        $mine = array_flip($emails);
        $now = $this->clock->now();
        $removal = [];
        foreach ($session->getWorkshop()?->getSessions() ?? [] as $other) {
            if ($other === $session || SessionStatus::Confirmed === $other->getStatus() || $other->getStartsAt() <= $now) {
                continue;
            }
            foreach ($other->getRegistrations() as $registration) {
                $email = mb_strtolower((string) $registration->getEmail());
                if (isset($mine[$email])) {
                    $removal[$email] = ($removal[$email] ?? 0) + 1;
                }
            }
        }

        return $removal;
    }

    /** @return list<string> */
    private function emails(WorkshopSession $session): array
    {
        $emails = [];
        foreach ($session->getRegistrations() as $registration) {
            $emails[] = mb_strtolower((string) $registration->getEmail());
        }

        return array_values(array_unique($emails));
    }
}
