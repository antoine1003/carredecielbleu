<?php

declare(strict_types=1);

namespace App\Controller\Dev;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Vitrine du design system, disponible uniquement en environnement de développement.
 */
final class StyleguideController extends AbstractController
{
    #[Route('/_styleguide', name: 'app_styleguide', env: 'dev')]
    public function __invoke(): Response
    {
        return $this->render('dev/styleguide.html.twig', [
            'palette' => [
                'encre', 'estuaire', 'ciel', 'brume', 'blanc', 'gris-bleu', 'galet',
                'trait', 'salicorne', 'salicorne-fonce', 'ambre', 'aurore', 'grenat',
            ],
            // [texte, fond, seuil minimal, usage]
            'contrastPairs' => [
                ['color-text', 'color-bg', 4.5, 'Texte courant'],
                ['color-text-muted', 'color-bg', 4.5, 'Texte secondaire'],
                ['color-link', 'color-bg', 4.5, 'Liens'],
                ['color-action-text', 'color-action-bg', 4.5, 'Bouton principal'],
                ['color-action-text', 'color-action-bg-hover', 4.5, 'Bouton principal survolé'],
                ['color-status-available', 'color-bg', 4.5, 'En stock'],
                ['color-status-made-to-order', 'color-bg', 4.5, 'Sur commande'],
                ['color-status-sold-out', 'color-bg', 4.5, 'Épuisé'],
                ['color-error', 'color-bg', 4.5, 'Erreurs'],
                ['color-text', 'color-surface', 4.5, 'Texte sur carte blanche'],
                ['color-field-border', 'color-surface', 3.0, 'Bordure de champ'],
                ['color-focus', 'color-bg', 3.0, 'Contour de focus'],
            ],
        ]);
    }
}
