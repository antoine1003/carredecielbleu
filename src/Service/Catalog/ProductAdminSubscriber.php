<?php

declare(strict_types=1);

namespace App\Service\Catalog;

use App\Entity\Product;
use App\Service\Image\ImageCollectionProcessor;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * À l'enregistrement d'un produit dans l'admin : convertit les photos envoyées en WebP
 * et range photos et déclinaisons dans l'ordre affiché (la première photo est la principale).
 */
final readonly class ProductAdminSubscriber
{
    public function __construct(
        private ImageCollectionProcessor $images,
    ) {
    }

    /** @param BeforeEntityPersistedEvent<object> $event */
    #[AsEventListener]
    public function onPersist(BeforeEntityPersistedEvent $event): void
    {
        $this->handle($event->getEntityInstance());
    }

    /** @param BeforeEntityUpdatedEvent<object> $event */
    #[AsEventListener]
    public function onUpdate(BeforeEntityUpdatedEvent $event): void
    {
        $this->handle($event->getEntityInstance());
    }

    private function handle(object $entity): void
    {
        if (!$entity instanceof Product) {
            return;
        }

        $this->images->handle($entity->getImages(), (string) $entity->getName());

        $position = 0;
        foreach ($entity->getVariants() as $variant) {
            $variant->setPosition($position++);
        }
    }
}
