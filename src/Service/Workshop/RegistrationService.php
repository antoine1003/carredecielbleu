<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\Registration;
use App\Entity\Workshop;
use App\Enum\SessionStatus;
use App\Repository\RegistrationRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Inscription à une ou plusieurs dates d'un atelier (sondage de disponibilités) et annulation.
 */
final readonly class RegistrationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RegistrationRepository $registrations,
        private SessionAvailability $availability,
        private WorkshopMailer $mailer,
        private DepositPolicy $deposit,
    ) {
    }

    /**
     * Crée une inscription par date cochée. Les dates fermées ou complètes entre-temps sont
     * ignorées et signalées ; une date déjà choisie avec le même email n'est pas dupliquée.
     */
    public function register(Workshop $workshop, RegistrationRequest $request): RegistrationResult
    {
        $email = mb_strtolower(trim((string) $request->email));

        [$created, $existing, $unavailable] = $this->entityManager->wrapInTransaction(
            function () use ($workshop, $request, $email): array {
                $created = $existing = $unavailable = [];
                $submissionId = bin2hex(random_bytes(16));
                $now = new \DateTimeImmutable();

                foreach ($request->sessions as $session) {
                    if ($session->getWorkshop() !== $workshop) {
                        continue;
                    }
                    // Verrou sur la date : deux inscriptions simultanées ne dépassent pas la capacité.
                    $this->entityManager->lock($session, LockMode::PESSIMISTIC_WRITE);
                    $this->entityManager->refresh($session);

                    $already = $this->registrations->findOneBy(['session' => $session, 'email' => $email]);
                    if (null !== $already) {
                        $existing[] = $already;
                        continue;
                    }
                    if (!$this->availability->acceptsRegistrations($session)) {
                        $unavailable[] = $session;
                        continue;
                    }

                    $registration = (new Registration($submissionId))
                        ->setFirstName(trim((string) $request->firstName))
                        ->setLastName(trim((string) $request->lastName))
                        ->setEmail($email)
                        ->setPhone('' === trim((string) $request->phone) ? null : trim((string) $request->phone))
                        ->setShowNamePublicly($request->showNamePublicly)
                        ->setTermsAcceptedAt($request->acceptTerms ? $now : null);
                    $session->addRegistration($registration);
                    $this->entityManager->persist($registration);
                    $created[] = $registration;
                }

                $this->entityManager->flush();

                return [$created, $existing, $unavailable];
            },
        );

        $emailSent = $this->mailer->sendRegistrationRecap([...$created, ...$existing]);

        return new RegistrationResult($created, $existing, $unavailable, $emailSent);
    }

    /**
     * Annulation par la personne (lien tokenisé) : l'inscription est supprimée (minimisation des données).
     * Date confirmée : l'artisane est prévenue par email (place libérée, acompte à rembourser ou non).
     */
    public function cancel(Registration $registration): CancellationResult
    {
        $session = $registration->getSession() ?? throw new \LogicException('Inscription sans date.');
        $depositPaid = $registration->isDepositPaid();
        $refundable = $this->deposit->isRefundableNow($session);

        if (SessionStatus::Confirmed === $session->getStatus()) {
            // Avant la suppression : le message reprend le nom et les coordonnées de la personne.
            $this->mailer->sendCancellationNotice($registration, $depositPaid, $refundable);
        }

        $session->removeRegistration($registration);
        $this->entityManager->remove($registration);
        $this->entityManager->flush();

        return new CancellationResult($session, $depositPaid, $depositPaid && $refundable);
    }
}
