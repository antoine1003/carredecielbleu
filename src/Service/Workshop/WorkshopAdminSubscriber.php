<?php

declare(strict_types=1);

namespace App\Service\Workshop;

use App\Entity\Workshop;
use App\Service\Image\ImageCollectionProcessor;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * À l'enregistrement d'un atelier dans l'admin : convertit les photos envoyées en WebP
 * et les range dans l'ordre affiché.
 */
final readonly class WorkshopAdminSubscriber
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
        if ($entity instanceof Workshop) {
            $this->images->handle($entity->getImages(), (string) $entity->getTitle());
        }
    }
}
