<?php

declare(strict_types=1);

namespace App\Service\Image;

use App\Entity\ProductImage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Photo supprimée de la base (retirée d'un produit, ou produit supprimé) : on supprime aussi ses fichiers WebP.
 */
#[AsEntityListener(event: Events::postRemove, method: 'postRemove', entity: ProductImage::class)]
final readonly class ProductImageFileRemover
{
    public function __construct(
        private ProductImageProcessor $processor,
    ) {
    }

    public function postRemove(ProductImage $image): void
    {
        if (null !== $image->getFilename()) {
            $this->processor->removeFiles($image->getFilename(), $image->getWidths());
        }
    }
}
