<?php

declare(strict_types=1);

namespace App\Entity;

use Symfony\Component\HttpFoundation\File\File;

/**
 * Photo envoyée depuis l'admin et convertie en WebP en plusieurs largeurs
 * (ImageProcessor). Implémentée par ProductImage et WorkshopImage via ResponsiveImageTrait.
 */
interface ResponsiveImage
{
    /** Sous-dossier de public/uploads/ où vivent les fichiers (ex. « products »). */
    public static function uploadFolder(): string;

    public function getFilename(): ?string;

    public function setFilename(?string $filename): static;

    public function getAlt(): ?string;

    public function setAlt(?string $alt): static;

    public function getWidth(): int;

    public function setWidth(int $width): static;

    public function getHeight(): int;

    public function setHeight(int $height): static;

    /** @return list<int> */
    public function getWidths(): array;

    /** @param list<int> $widths */
    public function setWidths(array $widths): static;

    public function setPosition(int $position): static;

    public function getFile(): ?File;

    public function setFile(?File $file): static;
}
