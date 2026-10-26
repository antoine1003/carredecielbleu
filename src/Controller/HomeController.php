<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Service\Catalog\ProductAvailability;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    private const int SELECTION_SIZE = 8;

    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductAvailability $availability,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function __invoke(): Response
    {
        // Produits mis en avant ; à défaut, les dernières créations disponibles.
        $selection = $this->availability->visibleViews($this->products->findVisibleFeatured());
        if ([] === $selection) {
            $selection = $this->availability->visibleViews($this->products->findVisible());
        }

        return $this->render('pages/home.html.twig', [
            'selection' => \array_slice($selection, 0, self::SELECTION_SIZE),
        ]);
    }
}
