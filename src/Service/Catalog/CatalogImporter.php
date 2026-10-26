<?php

declare(strict_types=1);

namespace App\Service\Catalog;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Enum\SyncStatus;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Synchronise le catalogue du site avec une source (CSV SumUp).
 *
 * Règles (voir CLAUDE.md) :
 * - rapprochement par identifiant SumUp, sinon par SKU ;
 * - l'import met à jour UNIQUEMENT : nom, description de base, catégorie, SEO SumUp, prix, stock, variantes ;
 * - il ne touche JAMAIS aux champs enrichis (photos, histoire, technique, dimensions, visibilité,
 *   lien d'achat, mise en avant, comportement épuisé, slug) ;
 * - à la création seulement : slug et visibilité initiale (« Display item in Online Store »).
 * - produit ou variante absent de la source → « non synchronisé », jamais supprimé.
 */
final readonly class CatalogImporter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProductRepository $products,
        private CategoryRepository $categories,
        private SlugGenerator $slugGenerator,
        private ClockInterface $clock,
    ) {
    }

    /** Calcule les changements sans rien enregistrer. */
    public function preview(ProductSourceInterface $source): ImportReport
    {
        return $this->run($source, false);
    }

    /** Applique les changements dans une transaction. */
    public function import(ProductSourceInterface $source): ImportReport
    {
        $data = $source->read();

        /** @var ImportReport */
        return $this->entityManager->wrapInTransaction(fn (): ImportReport => $this->sync($data, $source->getLabel(), true));
    }

    private function run(ProductSourceInterface $source, bool $apply): ImportReport
    {
        return $this->sync($source->read(), $source->getLabel(), $apply);
    }

    private function sync(CatalogData $data, string $label, bool $apply): ImportReport
    {
        $report = new ImportReport($label, $apply);
        $report->warnings = $data->warnings;
        $now = $this->clock->now();

        $index = $this->products->findImportIndex();
        $categories = $this->loadCategories();
        $reservedSlugs = array_fill_keys($this->products->findAllSlugs(), true);
        $seenProductIds = [];
        $seenKeys = [];

        foreach ($data->items as $item) {
            $key = $item->itemId ?? 'sku:'.$item->sku;
            if (isset($seenKeys[$key])) {
                $report->warnings[] = \sprintf('« %s » apparaît deux fois dans le fichier : seule la première occurrence est prise en compte.', $item->name);
                continue;
            }
            $seenKeys[$key] = true;

            $category = $this->resolveCategory($item->categoryName, $categories, $report, $apply);
            $product = (null !== $item->itemId ? ($index['byItemId'][$item->itemId] ?? null) : null)
                ?? (null !== $item->sku ? ($index['bySku'][$item->sku] ?? null) : null);

            if (null === $product) {
                $report->created[] = ['name' => $item->name, 'variants' => \count($item->variants), 'visible' => $item->displayedInOnlineStore];
                if ($apply) {
                    $product = $this->createProduct($item, $category, $reservedSlugs, $now);
                    $this->entityManager->persist($product);
                }
                continue;
            }

            if (null !== $product->getId()) {
                $seenProductIds[$product->getId()] = true;
            }
            if (SyncStatus::Manual === $product->getSyncStatus() && null === $product->getSumupItemId() && null === $item->itemId) {
                // Produit saisi à la main avec le même SKU : on le rattache à SumUp.
                $report->warnings[] = \sprintf('« %s » (saisi à la main) est rattaché à SumUp par son SKU.', $item->name);
            }

            $changes = $this->updateProduct($product, $item, $category, $apply, $now);
            if ([] === $changes) {
                ++$report->unchanged;
            } else {
                $report->updated[] = ['name' => $item->name, 'changes' => $changes];
            }
        }

        // Produits synchronisés absents de la source : non synchronisés, jamais supprimés.
        $known = [];
        foreach ([...array_values($index['byItemId']), ...array_values($index['bySku'])] as $product) {
            $known[(int) $product->getId()] = $product;
        }
        foreach ($known as $id => $product) {
            if (isset($seenProductIds[$id]) || SyncStatus::Synced !== $product->getSyncStatus()) {
                continue;
            }
            $report->notSynced[] = (string) $product->getName();
            if ($apply) {
                $product->setSyncStatus(SyncStatus::NotSynced);
            }
        }

        if ($apply) {
            $this->entityManager->flush();
        }

        return $report;
    }

    /**
     * @param array<string, true> $reservedSlugs
     */
    private function createProduct(CatalogItem $item, ?Category $category, array &$reservedSlugs, \DateTimeImmutable $now): Product
    {
        $slug = $this->slugGenerator->unique($item->name, $reservedSlugs);
        $reservedSlugs[$slug] = true;

        $product = (new Product())
            ->setSumupItemId($item->itemId)
            ->setSku($item->sku)
            ->setName($item->name)
            ->setDescription($item->description)
            ->setCategory($category)
            ->setSumupSeoTitle($item->seoTitle)
            ->setSumupSeoDescription($item->seoDescription)
            ->setSlug($slug)
            ->setVisible($item->displayedInOnlineStore)
            ->setSyncStatus(SyncStatus::Synced)
            ->setLastSyncedAt($now);

        foreach ($item->variants as $position => $variantData) {
            $product->addVariant($this->applyVariant(new ProductVariant(), $variantData, $position));
        }

        return $product;
    }

    /**
     * @return list<array{field: string, old: string, new: string}>
     */
    private function updateProduct(Product $product, CatalogItem $item, ?Category $category, bool $apply, \DateTimeImmutable $now): array
    {
        $changes = [];
        $track = function (string $label, mixed $old, mixed $new, callable $setter) use (&$changes, $apply): void {
            if ($old === $new) {
                return;
            }
            $changes[] = ['field' => $label, 'old' => $this->display($old), 'new' => $this->display($new)];
            if ($apply) {
                $setter($new);
            }
        };

        $track('Identifiant SumUp', $product->getSumupItemId(), $item->itemId ?? $product->getSumupItemId(), $product->setSumupItemId(...));
        $track('SKU', $product->getSku(), $item->sku ?? $product->getSku(), $product->setSku(...));
        $track('Nom', $product->getName(), $item->name, $product->setName(...));
        if ($product->getDescription() !== $item->description) {
            $changes[] = ['field' => 'Description', 'old' => null === $product->getDescription() ? '—' : 'ancienne version', 'new' => null === $item->description ? '—' : 'nouvelle version'];
            if ($apply) {
                $product->setDescription($item->description);
            }
        }
        // Comparaison par entité : le nom affiché d'une catégorie peut avoir été modifié dans l'admin.
        if ($product->getCategory() !== $category) {
            $changes[] = ['field' => 'Catégorie', 'old' => $this->display($product->getCategory()?->getName()), 'new' => $this->display($category?->getName())];
            if ($apply) {
                $product->setCategory($category);
            }
        }
        $track('Titre SEO SumUp', $product->getSumupSeoTitle(), $item->seoTitle, $product->setSumupSeoTitle(...));
        $track('Description SEO SumUp', $product->getSumupSeoDescription(), $item->seoDescription, $product->setSumupSeoDescription(...));
        $track('Synchronisation', $product->getSyncStatus()->label(), SyncStatus::Synced->label(), static fn () => $product->setSyncStatus(SyncStatus::Synced));

        $changes = [...$changes, ...$this->syncVariants($product, $item, $apply)];

        if ($apply) {
            $product->setLastSyncedAt($now);
        }

        return $changes;
    }

    /**
     * @return list<array{field: string, old: string, new: string}>
     */
    private function syncVariants(Product $product, CatalogItem $item, bool $apply): array
    {
        $changes = [];
        $existing = [];
        foreach ($product->getVariants() as $variant) {
            $existing[$variant->getSumupVariantId() ?? 'name:'.$variant->getName()] = $variant;
        }

        $seen = [];
        foreach ($item->variants as $position => $data) {
            $key = $data->variantId ?? 'name:'.$data->name;
            $seen[$key] = true;
            $label = null === $data->name ? '' : ' « '.$data->name.' »';
            $variant = $existing[$key] ?? null;

            if (null === $variant) {
                $changes[] = ['field' => 'Variante'.$label, 'old' => '—', 'new' => 'ajoutée'];
                if ($apply) {
                    $product->addVariant($this->applyVariant(new ProductVariant(), $data, $position));
                }
                continue;
            }

            $before = [$variant->getName(), $variant->getPriceCents(), $variant->isTrackInventory(), $variant->getStock(), $variant->getSyncStatus()];
            $fields = [
                ['Nom de variante', $variant->getName(), $data->name],
                ['Prix'.$label, $this->price($variant->getPriceCents()), $this->price($data->priceCents)],
                ['Stock suivi'.$label, $variant->isTrackInventory(), $data->trackInventory],
                ['Stock'.$label, $variant->getStock(), $data->stock],
            ];
            foreach ($fields as [$field, $old, $new]) {
                if ($old !== $new) {
                    $changes[] = ['field' => $field, 'old' => $this->display($old), 'new' => $this->display($new)];
                }
            }
            if ($apply && $before !== [$data->name, $data->priceCents, $data->trackInventory, $data->stock, SyncStatus::Synced]) {
                $this->applyVariant($variant, $data, $position);
            } elseif ($apply) {
                $variant->setPosition($position);
            }
        }

        foreach ($existing as $key => $variant) {
            if (isset($seen[$key]) || SyncStatus::Synced !== $variant->getSyncStatus()) {
                continue;
            }
            $changes[] = ['field' => 'Variante « '.($variant->getName() ?? 'unique').' »', 'old' => 'synchronisée', 'new' => 'non synchronisée'];
            if ($apply) {
                $variant->setSyncStatus(SyncStatus::NotSynced);
            }
        }

        return $changes;
    }

    private function applyVariant(ProductVariant $variant, CatalogVariant $data, int $position): ProductVariant
    {
        return $variant
            ->setSumupVariantId($data->variantId)
            ->setName($data->name)
            ->setPriceCents($data->priceCents)
            ->setTrackInventory($data->trackInventory)
            ->setStock($data->stock)
            ->setPosition($position)
            ->setSyncStatus(SyncStatus::Synced);
    }

    /**
     * @param array<string, Category> $categories indexées par nom SumUp (mis à jour au fil de l'eau)
     */
    private function resolveCategory(?string $name, array &$categories, ImportReport $report, bool $apply): ?Category
    {
        if (null === $name) {
            return null;
        }
        if (isset($categories[$name])) {
            return $categories[$name];
        }

        $report->categoriesCreated[] = $name;
        $category = (new Category())
            ->setName($name)
            ->setSumupName($name)
            ->setSlug($this->slugGenerator->unique($name, $this->categorySlugs($categories)));
        if ($apply) {
            $this->entityManager->persist($category);
        }
        $categories[$name] = $category;

        return $category;
    }

    /** @return array<string, Category> */
    private function loadCategories(): array
    {
        $byName = [];
        foreach ($this->categories->findAll() as $category) {
            $byName[$category->getSumupName() ?? '#'.$category->getId()] = $category;
        }

        return $byName;
    }

    /**
     * @param array<string, Category> $categories
     *
     * @return array<string, true>
     */
    private function categorySlugs(array $categories): array
    {
        $slugs = [];
        foreach ($categories as $category) {
            $slugs[(string) $category->getSlug()] = true;
        }

        return $slugs;
    }

    private function price(?int $cents): ?string
    {
        return null === $cents ? null : number_format($cents / 100, 2, ',', ' ').' €';
    }

    private function display(mixed $value): string
    {
        return match (true) {
            null === $value, '' === $value => '—',
            true === $value => 'oui',
            false === $value => 'non',
            \is_scalar($value) => (string) $value,
            default => get_debug_type($value),
        };
    }
}
