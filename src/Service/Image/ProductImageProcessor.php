<?php

declare(strict_types=1);

namespace App\Service\Image;

use App\Entity\ProductImage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Photos produits : vérifie le fichier envoyé, le convertit en WebP en plusieurs largeurs
 * (srcset) et le range sous public/uploads/products/{nom aléatoire}-{largeur}.webp.
 * L'original n'est jamais conservé ni servi.
 */
final readonly class ProductImageProcessor
{
    public const array ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    public const int MAX_BYTES = 10 * 1024 * 1024;

    /** Largeurs générées (jamais d'agrandissement au-delà de l'original). */
    private const array WIDTHS = [480, 960, 1600];
    private const int MAX_SOURCE_PIXELS = 50_000_000; // protège contre les images « bombes » (ex. 10 000 × 5 000)
    private const int WEBP_QUALITY = 82;

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/uploads/products')]
        private string $directory,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * Traite le fichier et renseigne l'image (nom, dimensions, largeurs disponibles).
     *
     * @throws ImageProcessingException fichier refusé (message destiné à l'admin)
     */
    public function process(File $file, ProductImage $image): void
    {
        $source = $this->load($file);
        $previous = $image->getFilename();

        try {
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            $widths = array_values(array_filter(self::WIDTHS, static fn (int $w): bool => $w <= $sourceWidth));
            if ([] === $widths) {
                $widths = [$sourceWidth];
            }

            $this->filesystem->mkdir($this->directory, 0o755);
            $filename = bin2hex(random_bytes(16));
            $largestHeight = $sourceHeight;

            foreach ($widths as $width) {
                $height = (int) round($sourceHeight * $width / $sourceWidth);
                $resized = $width === $sourceWidth ? $source : imagescale($source, $width, $height, \IMG_BICUBIC);
                if (false === $resized) {
                    throw new ImageProcessingException('Impossible de redimensionner la photo.');
                }
                imagepalettetotruecolor($resized);
                imagealphablending($resized, true);
                imagesavealpha($resized, true);
                if (!imagewebp($resized, $this->path($filename, $width), self::WEBP_QUALITY)) {
                    throw new ImageProcessingException('Impossible d\'enregistrer la photo.');
                }
                $largestHeight = $height;
            }
        } finally {
            imagedestroy($source);
        }

        $image->setFilename($filename)
            ->setWidth(max($widths))
            ->setHeight($largestHeight)
            ->setWidths($widths);

        // Remplacement d'une photo existante : on supprime les anciens fichiers.
        if (null !== $previous && $previous !== $filename) {
            $this->removeFiles($previous, []);
        }
    }

    /**
     * Supprime les fichiers WebP d'une photo.
     *
     * @param list<int> $widths largeurs connues ; vide = toutes celles trouvées sur le disque
     */
    public function removeFiles(string $filename, array $widths): void
    {
        if (1 !== preg_match('/^[a-f0-9]{32}$/', $filename)) {
            return;
        }
        $paths = [] === $widths
            ? (glob($this->directory.'/'.$filename.'-*.webp') ?: [])
            : array_map(fn (int $w): string => $this->path($filename, $w), $widths);
        $this->filesystem->remove($paths);
    }

    /**
     * @throws ImageProcessingException
     */
    private function load(File $file): \GdImage
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw new ImageProcessingException('La photo dépasse 10 Mo.');
        }
        $mime = $file->getMimeType();
        if (!\in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw new ImageProcessingException('Format non accepté : envoyez une photo JPEG, PNG ou WebP.');
        }
        $info = @getimagesize($file->getPathname());
        if (false === $info || $info[0] < 1 || $info[1] < 1) {
            throw new ImageProcessingException('Ce fichier n\'est pas une image lisible.');
        }
        if ($info[0] * $info[1] > self::MAX_SOURCE_PIXELS) {
            throw new ImageProcessingException('La photo est trop grande (50 millions de pixels maximum).');
        }

        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file->getPathname()),
            'image/png' => @imagecreatefrompng($file->getPathname()),
            default => @imagecreatefromwebp($file->getPathname()),
        };
        if (!$image instanceof \GdImage) {
            throw new ImageProcessingException('Ce fichier n\'est pas une image lisible.');
        }

        return 'image/jpeg' === $mime ? $this->applyExifOrientation($image, $file->getPathname()) : $image;
    }

    /** Les photos de téléphone sont souvent stockées « couchées » avec une indication EXIF : on les redresse. */
    private function applyExifOrientation(\GdImage $image, string $path): \GdImage
    {
        if (!\function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($path);
        $orientation = \is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated instanceof \GdImage ? $rotated : $image;
    }

    private function path(string $filename, int $width): string
    {
        return \sprintf('%s/%s-%d.webp', $this->directory, $filename, $width);
    }
}
