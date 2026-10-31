<?php

declare(strict_types=1);

namespace App\Service\Image;

use App\Entity\ResponsiveImage;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

/**
 * Photos d'un produit ou d'un atelier enregistré dans l'admin : convertit les fichiers envoyés,
 * complète le texte alternatif et range les photos dans l'ordre affiché (la première est la principale).
 */
final readonly class ImageCollectionProcessor
{
    public function __construct(
        private ImageProcessor $processor,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @template T of ResponsiveImage
     *
     * @param Collection<int, T> $images
     * @param string             $defaultAlt texte alternatif des photos laissées sans description
     */
    public function handle(Collection $images, string $defaultAlt): void
    {
        $position = 0;
        foreach ($images->toArray() as $image) {
            $file = $image->getFile();
            if (null !== $file) {
                try {
                    $this->processor->process($file, $image);
                } catch (ImageProcessingException $e) {
                    // Photo illisible malgré la validation : on l'écarte sans bloquer le reste.
                    $images->removeElement($image);
                    $this->flash(\sprintf('Une photo n\'a pas été enregistrée : %s', $e->getMessage()));
                    continue;
                } finally {
                    $image->setFile(null);
                }
            }
            if (null === $image->getAlt() || '' === trim($image->getAlt())) {
                $image->setAlt($defaultAlt);
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
