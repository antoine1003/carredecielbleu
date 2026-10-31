<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\Registration;
use App\Entity\WorkshopSession;
use App\Service\SiteSettingsProvider;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Emails des ateliers (envoi synchrone, voir CLAUDE.md).
 * Un échec d'envoi est journalisé et signalé à l'appelant, sans annuler l'opération en cours.
 */
final readonly class WorkshopMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urls,
        private LoggerInterface $logger,
        private SiteSettingsProvider $settings,
        #[Autowire('%env(MAIL_FROM_ADDRESS)%')]
        private string $fromAddress,
        #[Autowire('%env(MAIL_FROM_NAME)%')]
        private string $fromName,
    ) {
    }

    /**
     * Récapitulatif après inscription, avec un lien d'annulation par date.
     *
     * @param list<Registration> $registrations inscriptions d'une même personne
     */
    public function sendRegistrationRecap(array $registrations): bool
    {
        if ([] === $registrations) {
            return true;
        }
        $first = $registrations[0];

        return $this->send(
            (string) $first->getEmail(),
            'Votre inscription à l\'atelier « '.$first->getSession()?->getWorkshop()?->getTitle().' »',
            'emails/workshop/registration_recap',
            [
                'firstName' => $first->getFirstName(),
                'workshop' => $first->getSession()?->getWorkshop(),
                'items' => array_map(fn (Registration $r): array => [
                    'session' => $r->getSession(),
                    'cancelUrl' => $this->cancelUrl($r),
                ], $registrations),
            ],
        );
    }

    /** La date à laquelle la personne est inscrite est confirmée. */
    public function sendSessionConfirmed(Registration $registration): bool
    {
        $session = $registration->getSession();

        return $this->send(
            (string) $registration->getEmail(),
            'C\'est confirmé : atelier « '.$session?->getWorkshop()?->getTitle().' »',
            'emails/workshop/session_confirmed',
            [
                'firstName' => $registration->getFirstName(),
                'session' => $session,
                'workshop' => $session?->getWorkshop(),
                'cancelUrl' => $this->cancelUrl($registration),
            ],
        );
    }

    /** Une autre date que celle(s) choisie(s) par la personne a été retenue. */
    public function sendOtherSessionChosen(Registration $registration, WorkshopSession $confirmed, bool $otherDatesCancelled): bool
    {
        return $this->send(
            (string) $registration->getEmail(),
            'Atelier « '.$confirmed->getWorkshop()?->getTitle().' » : la date est fixée',
            'emails/workshop/other_session_chosen',
            [
                'firstName' => $registration->getFirstName(),
                'session' => $confirmed,
                'workshop' => $confirmed->getWorkshop(),
                'otherDatesCancelled' => $otherDatesCancelled,
                'workshopUrl' => $this->urls->generate('app_workshop', ['slug' => $confirmed->getWorkshop()?->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'cancelUrl' => $this->cancelUrl($registration),
            ],
        );
    }

    /** Relance : l'acompte n'est pas encore arrivé. */
    public function sendDepositReminder(Registration $registration): bool
    {
        $session = $registration->getSession();

        return $this->send(
            (string) $registration->getEmail(),
            'Atelier « '.$session?->getWorkshop()?->getTitle().' » : votre acompte',
            'emails/workshop/deposit_reminder',
            [
                'firstName' => $registration->getFirstName(),
                'session' => $session,
                'workshop' => $session?->getWorkshop(),
                'cancelUrl' => $this->cancelUrl($registration),
            ],
        );
    }

    /**
     * Prévient l'artisane qu'une personne d'une date confirmée a annulé (acompte à rembourser ou conservé).
     * Appelé avant la suppression de l'inscription : les informations sont copiées dans le message.
     */
    public function sendCancellationNotice(Registration $registration, bool $depositPaid, bool $refundable): bool
    {
        $session = $registration->getSession();
        $to = $this->settings->get()->getContactEmail() ?? $this->fromAddress;

        return $this->send(
            $to,
            'Annulation : '.$registration->getFirstName().' '.$registration->getLastName().' (« '.$session?->getWorkshop()?->getTitle().' »)',
            'emails/workshop/cancellation_notice',
            [
                'registration' => $registration,
                'session' => $session,
                'workshop' => $session?->getWorkshop(),
                'depositPaid' => $depositPaid,
                'refundable' => $refundable,
                'adminUrl' => $this->urls->generate('admin_workshop_planning', ['id' => $session?->getWorkshop()?->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
        );
    }

    private function cancelUrl(Registration $registration): string
    {
        return $this->urls->generate('app_workshop_cancel', ['token' => $registration->getCancelToken()], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function send(string $to, string $subject, string $template, array $context): bool
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to($to)
            ->subject($subject)
            ->htmlTemplate($template.'.html.twig')
            ->textTemplate($template.'.txt.twig')
            ->context($context);

        try {
            $this->mailer->send($email);

            return true;
        } catch (TransportExceptionInterface $e) {
            // Pas l'adresse en clair dans les logs (RGPD) : seulement le modèle et l'erreur.
            $this->logger->error('Envoi d\'email d\'atelier impossible ({template}) : {message}', [
                'template' => $template,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
