<?php

declare(strict_types=1);

namespace App\Service\Image;

use App\Entity\Product;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

/**
 * À l'enregistrement d'un produit dans l'admin : convertit les photos envoyées en WebP
 * et range les photos dans l'ordre affiché (la première est la photo principale).
 */
final readonly class ProductImageUploadSubscriber
{
    public function __construct(
        private ProductImageProcessor $processor,
        private RequestStack $requestStack,
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

        $position = 0;
        foreach ($entity->getImages()->toArray() as $image) {
            $file = $image->getFile();
            if (null !== $file) {
                try {
                    $this->processor->process($file, $image);
                } catch (ImageProcessingException $e) {
                    // Photo illisible malgré la validation : on l'écarte sans bloquer le reste du produit.
                    $entity->removeImage($image);
                    $this->flash(\sprintf('Une photo n\'a pas été enregistrée : %s', $e->getMessage()));
                    continue;
                } finally {
                    $image->setFile(null);
                }
            }
            if (null === $image->getAlt() || '' === trim($image->getAlt())) {
                $image->setAlt((string) $entity->getName());
            }
            $image->setPosition($position++);
        }
    }

    private function flash(string $message): void
    {
        $session = $this->requestStack->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('warning', $message);
        }
    }
}
