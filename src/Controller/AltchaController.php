<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AntiSpam\AltchaGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class AltchaController extends AbstractController
{
    /** Nouveau défi pour le widget anti-robots (jamais mis en cache). */
    #[Route('/altcha/defi', name: 'app_altcha_challenge', methods: ['GET'])]
    public function challenge(AltchaGuard $guard): JsonResponse
    {
        $response = new JsonResponse($guard->createChallenge());
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
