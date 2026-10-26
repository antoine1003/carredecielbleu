<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Service\Catalog\ProductAvailability;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/boutique')]
final class ShopController extends AbstractController
{
    private const string SLUG = '[a-z0-9]+(?:-[a-z0-9]+)*';
    private const int RELATED_SIZE = 4;

    public function __construct(
        private readonly ProductRepository $products,
        private readonly CategoryRepository $categories,
        private readonly ProductAvailability $availability,
    ) {
    }

    #[Route('', name: 'app_shop', methods: ['GET'])]
    public function index(): Response
    {
        return $this->renderList(null);
    }

    #[Route('/categorie/{slug}', name: 'app_shop_category', requirements: ['slug' => self::SLUG], methods: ['GET'])]
    public function category(string $slug): Response
    {
        $category = $this->categories->findOneBy(['slug' => $slug, 'visible' => true])
            ?? throw $this->createNotFoundException('Catégorie introuvable.');

        return $this->renderList($category);
    }

    #[Route('/{slug}', name: 'app_product', requirements: ['slug' => self::SLUG], methods: ['GET'])]
    public function show(string $slug): Response
    {
        $product = $this->products->findOneVisibleBySlug($slug);
        $view = null === $product ? null : $this->availability->view($product);
        if (null === $view || !$view->isVisible()) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        $related = [];
        if (null !== $product->getCategory()) {
            $related = array_filter(
                $this->availability->visibleViews($this->products->findVisible($product->getCategory())),
                static fn ($other): bool => $other->product !== $product,
            );
        }

        return $this->render('shop/show.html.twig', [
            'view' => $view,
            'related' => \array_slice(array_values($related), 0, self::RELATED_SIZE),
        ]);
    }

    private function renderList(?Category $category): Response
    {
        return $this->render('shop/index.html.twig', [
            'category' => $category,
            'categories' => $this->categories->findBy(['visible' => true], ['position' => 'ASC', 'name' => 'ASC']),
            'views' => $this->availability->visibleViews($this->products->findVisible($category)),
        ]);
    }
}
