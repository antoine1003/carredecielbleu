<?php

declare(strict_types=1);

namespace App\Service\Catalog;

use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Génère des slugs lisibles et uniques (« vitrail-douce-meduse », « vitrail-douce-meduse-2 »).
 */
final readonly class SlugGenerator
{
    public function __construct(
        private SluggerInterface $slugger,
    ) {
    }

    public function slugify(string $text): string
    {
        $slug = $this->slugger->slug($text, '-', 'fr')->lower()->toString();
        $slug = trim((string) preg_replace('/-+/', '-', $slug), '-');

        return '' === $slug ? 'sans-titre' : mb_substr($slug, 0, 140);
    }

    /**
     * @param array<string, true> $taken slugs déjà utilisés
     */
    public function unique(string $text, array $taken): string
    {
        $base = $this->slugify($text);
        $slug = $base;
        $i = 2;
        while (isset($taken[$slug])) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
