<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Produits visibles (le filtrage par disponibilité se fait ensuite dans ProductAvailability).
     *
     * @return list<Product>
     */
    public function findVisible(?Category $category = null): array
    {
        $qb = $this->createVisibleQueryBuilder();

        if (null !== $category) {
            $qb->andWhere('p.category = :category')->setParameter('category', $category);
        }

        /** @var list<Product> */
        return $qb->getQuery()->getResult();
    }

    /** @return list<Product> */
    public function findVisibleFeatured(): array
    {
        /** @var list<Product> */
        return $this->createVisibleQueryBuilder()
            ->andWhere('p.featured = true')
            ->getQuery()
            ->getResult();
    }

    public function findOneVisibleBySlug(string $slug): ?Product
    {
        /** @var Product|null */
        return $this->createVisibleQueryBuilder()
            ->andWhere('p.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Index des produits synchronisables, par identifiant SumUp et par SKU.
     *
     * @return array{byItemId: array<string, Product>, bySku: array<string, Product>}
     */
    public function findImportIndex(): array
    {
        /** @var list<Product> $products */
        $products = $this->createQueryBuilder('p')
            ->leftJoin('p.variants', 'v')->addSelect('v')
            ->leftJoin('p.category', 'c')->addSelect('c')
            ->getQuery()
            ->getResult();

        $index = ['byItemId' => [], 'bySku' => []];
        foreach ($products as $product) {
            if (null !== $product->getSumupItemId()) {
                $index['byItemId'][$product->getSumupItemId()] = $product;
            }
            if (null !== $product->getSku()) {
                $index['bySku'][$product->getSku()] = $product;
            }
        }

        return $index;
    }

    /** @return list<string> */
    public function findAllSlugs(): array
    {
        /** @var list<string> */
        return $this->createQueryBuilder('p')->select('p.slug')->getQuery()->getSingleColumnResult();
    }

    private function createVisibleQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.variants', 'v')->addSelect('v')
            ->leftJoin('p.images', 'i')->addSelect('i')
            ->leftJoin('p.category', 'c')->addSelect('c')
            ->andWhere('p.visible = true')
            ->orderBy('p.createdAt', 'DESC')
            ->addOrderBy('p.id', 'DESC');
    }
}
