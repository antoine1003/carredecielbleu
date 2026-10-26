<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SiteSetting;
use App\Repository\SiteSettingRepository;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Accès aux réglages du site (une seule ligne). Tant qu'elle n'existe pas, renvoie
 * des valeurs par défaut (placeholders explicites) sans rien enregistrer.
 */
final class SiteSettingsProvider implements ResetInterface
{
    private ?SiteSetting $current = null;

    public function __construct(
        private readonly SiteSettingRepository $repository,
    ) {
    }

    public function get(): SiteSetting
    {
        return $this->current ??= $this->repository->findCurrent() ?? new SiteSetting();
    }

    public function reset(): void
    {
        $this->current = null;
    }
}
