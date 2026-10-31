<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\Workshop\DepositPolicy;
use App\Service\Workshop\SessionAvailability;
use App\Service\Workshop\WorkshopOverview;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Disponibilité des dates d'ateliers (gabarits publics), règles de l'acompte (public, emails, admin)
 * et vue d'ensemble d'un atelier (admin).
 */
final class WorkshopExtension extends AbstractExtension
{
    public function __construct(
        private readonly SessionAvailability $availability,
        private readonly WorkshopOverview $overview,
        private readonly DepositPolicy $deposit,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('session_count', $this->availability->registeredCount(...)),
            new TwigFunction('session_remaining', $this->availability->remainingPlaces(...)),
            new TwigFunction('session_is_open', $this->availability->acceptsRegistrations(...)),
            new TwigFunction('session_public_names', $this->availability->publicNames(...)),
            new TwigFunction('workshop_overview', $this->overview->of(...)),
            // Règles de l'acompte : deposit_policy().isRequired(workshop), .paymentDeadline(session)…
            new TwigFunction('deposit_policy', fn (): DepositPolicy => $this->deposit),
        ];
    }
}
