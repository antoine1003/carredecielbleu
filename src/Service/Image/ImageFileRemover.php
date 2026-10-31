<?php

declare(strict_types=1);

namespace App\Service\Image;

use App\Entity\ProductImage;
use App\Entity\ResponsiveImage;
use App\Entity\WorkshopImage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Photo supprimée de la base (retirée, ou produit/atelier supprimé) : on supprime aussi ses fichiers WebP.
 */
#[AsEntityListener(event: Events::postRemove, method: 'postRemove', entity: ProductImage::class)]
#[AsEntityListener(event: Events::postRemove, method: 'postRemove', entity: WorkshopImage::class)]
final readonly class ImageFileRemover
{
    public function __construct(
        private ImageProcessor $processor,
    ) {
    }

    public function postRemove(ResponsiveImage $image): void
    {
        $this->processor->removeFiles($image);
    }
}
