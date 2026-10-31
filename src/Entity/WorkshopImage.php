<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Photo d'un atelier : réalisations, lieu, ambiance (fichiers dans public/uploads/workshops/).
 */
#[ORM\Entity]
class WorkshopImage implements ResponsiveImage
{
    use ResponsiveImageTrait;

    #[ORM\ManyToOne(inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Workshop $workshop = null;

    public static function uploadFolder(): string
    {
        return 'workshops';
    }

    public function getWorkshop(): ?Workshop
    {
        return $this->workshop;
    }

    public function setWorkshop(?Workshop $workshop): static
    {
        $this->workshop = $workshop;

        return $this;
    }
}
