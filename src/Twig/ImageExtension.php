<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Product;
use App\Entity\ResponsiveImage;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Attributs d'image (src, srcset, dimensions) des photos en WebP,
 * stockées dans public/uploads/{dossier}/{nom}-{largeur}.webp.
 */
final class ImageExtension extends AbstractExtension
{
    public const string PUBLIC_DIR = '/uploads';

    /** Motif d'illustration provisoire selon la catégorie SumUp. */
    private const array MOTIFS = [
        'Insectes' => 'insecte',
        'Méduses' => 'insecte',
        'Oiseaux' => 'insecte',
        'Bouquet' => 'fleurs',
        'Fleurs' => 'fleurs',
        'Vitrail végétal' => 'fleurs',
        'Attrapes soleil' => 'lune',
    ];

    public function getFunctions(): array
    {
        return [
            new TwigFunction('image_attrs', $this->imageAttrs(...)),
            new TwigFunction('glass_motif', $this->glassMotif(...)),
        ];
    }

    public static function url(ResponsiveImage $image, int $width): string
    {
        return \sprintf('%s/%s/%s-%d.webp', self::PUBLIC_DIR, $image::uploadFolder(), $image->getFilename(), $width);
    }

    /**
     * @return array{src: string, large: string, srcset: string, sizes: string, width: int, height: int, alt: string}|null
     */
    public function imageAttrs(?ResponsiveImage $image, string $sizes = '(min-width: 74rem) 18rem, (min-width: 48rem) 33vw, 100vw'): ?array
    {
        if (null === $image || null === $image->getFilename() || [] === $image->getWidths()) {
            return null;
        }

        $widths = $image->getWidths();
        sort($widths);

        return [
            'src' => self::url($image, $widths[(int) floor((\count($widths) - 1) / 2)]),
            // Plus grande version (visionneuse plein écran, lien sans JavaScript).
            'large' => self::url($image, $widths[\count($widths) - 1]),
            'srcset' => implode(', ', array_map(static fn (int $w): string => self::url($image, $w).' '.$w.'w', $widths)),
            'sizes' => $sizes,
            'width' => $image->getWidth(),
            'height' => $image->getHeight(),
            'alt' => (string) $image->getAlt(),
        ];
    }

    public function glassMotif(Product $product): string
    {
        $category = $product->getCategory()?->getSumupName();

        return self::MOTIFS[$category] ?? 'defaut';
    }
}
