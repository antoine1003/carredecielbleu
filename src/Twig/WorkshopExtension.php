<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\Workshop\SessionAvailability;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Disponibilité des dates d'ateliers pour les gabarits publics.
 */
final class WorkshopExtension extends AbstractExtension
{
    public function __construct(
        private readonly SessionAvailability $availability,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('session_count', $this->availability->registeredCount(...)),
            new TwigFunction('session_remaining', $this->availability->remainingPlaces(...)),
            new TwigFunction('session_is_open', $this->availability->acceptsRegistrations(...)),
            new TwigFunction('session_public_names', $this->availability->publicNames(...)),
        ];
    }
}
