<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Product;
use App\Entity\ProductImage;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Attributs d'image (src, srcset, dimensions) des photos produits en WebP,
 * stockées dans public/uploads/products/{nom}-{largeur}.webp.
 */
final class ProductImageExtension extends AbstractExtension
{
    public const string PUBLIC_DIR = '/uploads/products';

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
            new TwigFunction('product_image', $this->productImage(...)),
            new TwigFunction('glass_motif', $this->glassMotif(...)),
        ];
    }

    /**
     * @return array{src: string, srcset: string, sizes: string, width: int, height: int, alt: string}|null
     */
    public function productImage(?ProductImage $image, string $sizes = '(min-width: 74rem) 18rem, (min-width: 48rem) 33vw, 100vw'): ?array
    {
        if (null === $image || null === $image->getFilename() || [] === $image->getWidths()) {
            return null;
        }

        $widths = $image->getWidths();
        sort($widths);
        $url = static fn (int $width): string => \sprintf('%s/%s-%d.webp', self::PUBLIC_DIR, $image->getFilename(), $width);

        return [
            'src' => $url($widths[(int) floor((\count($widths) - 1) / 2)]),
            'srcset' => implode(', ', array_map(static fn (int $w): string => $url($w).' '.$w.'w', $widths)),
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
