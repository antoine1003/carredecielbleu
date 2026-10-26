<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\PageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageController extends AbstractController
{
    /** Priorité basse : les routes du site passent avant les pages de contenu. */
    #[Route('/{slug}', name: 'app_page', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'], priority: -10)]
    public function show(string $slug, PageRepository $pages): Response
    {
        $page = $pages->findOnePublishedBySlug($slug) ?? throw $this->createNotFoundException('Page introuvable.');

        return $this->render('page/show.html.twig', ['page' => $page]);
    }
}
