<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Enum\SyncStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

/**
 * Produits : les champs SumUp sont en lecture seule (l'import les remplace),
 * seuls les champs enrichis sont modifiables.
 *
 * @extends AbstractCrudController<Product>
 */
final class ProductCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('produit')
            ->setEntityLabelInPlural('produits')
            ->setPageTitle(Crud::PAGE_INDEX, 'Produits')
            ->setPageTitle(Crud::PAGE_EDIT, static fn (Product $product): string => (string) $product->getName())
            ->setPageTitle(Crud::PAGE_DETAIL, static fn (Product $product): string => (string) $product->getName())
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name', 'sku', 'slug', 'category.name'])
            ->setPaginatorPageSize(50)
            ->setHelp(Crud::PAGE_INDEX, 'Les produits viennent de l\'import SumUp. Ici, vous ajoutez ce que SumUp ne connaît pas : lien d\'achat, photos, histoire, visibilité.');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('category')
            ->add('visible')
            ->add('featured')
            ->add('syncStatus');
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addTab('Sur le site', 'fa fa-globe');
        yield TextField::new('name', 'Nom')
            ->setDisabled()
            ->setHelp('Vient de SumUp : modifiez-le dans SumUp, il sera mis à jour au prochain import.');
        yield BooleanField::new('visible', 'Visible')->renderAsSwitch()
            ->setHelp('Un produit visible n\'apparaît que s\'il est disponible (voir le réglage « épuisé »).');
        yield BooleanField::new('featured', 'En avant')->renderAsSwitch()->hideOnIndex()
            ->setHelp('Affiché sur la page d\'accueil.');
        yield UrlField::new('purchaseUrl', 'Lien d\'achat SumUp')->hideOnIndex()
            ->setHelp('Adresse de la fiche produit sur SumUp (https://…). Sans lien, le bouton devient « Me contacter ».');
        yield ChoiceField::new('soldOutBehavior', 'Quand le stock est à zéro')->hideOnIndex()
            ->setFormTypeOption('placeholder', 'Utiliser le réglage général du site')
            ->setRequired(false);
        yield SlugField::new('slug', 'Adresse de la page')->setTargetFieldName('name')->hideOnIndex()
            ->setUnlockConfirmationMessage('Changer l\'adresse casse les liens déjà partagés. Continuer ?')
            ->setHelp('Partie de l\'adresse après /boutique/.');
        yield TextField::new('technique', 'Technique')->hideOnIndex();
        yield TextField::new('dimensions', 'Dimensions')->hideOnIndex();
        yield TextEditorField::new('story', 'Histoire de la pièce')->hideOnIndex()
            ->setHelp('Texte affiché en plus de la description SumUp.');

        yield FormField::addTab('Données SumUp', 'fa fa-cash-register')
            ->setHelp('Remplacées à chaque import : modifiez-les dans SumUp.');
        yield AssociationField::new('category', 'Catégorie')->setDisabled();
        // Colonne calculée : adossée à l'id, mise en forme à partir des variantes.
        yield IntegerField::new('id', 'Prix et stock')
            ->hideOnForm()
            ->setSortable(false)
            ->formatValue(fn ($value, Product $product): string => $this->summarizeVariants($product));
        yield TextEditorField::new('description', 'Description SumUp')->onlyOnDetail();
        yield TextField::new('sumupSeoTitle', 'Titre SEO SumUp')->onlyOnDetail();
        yield TextareaField::new('sumupSeoDescription', 'Description SEO SumUp')->onlyOnDetail();
        yield TextField::new('sku', 'SKU')->onlyOnDetail();
        yield TextField::new('sumupItemId', 'Identifiant SumUp')->onlyOnDetail();
        yield ChoiceField::new('syncStatus', 'Synchronisation')
            ->setDisabled()
            ->renderAsBadges([
                SyncStatus::Synced->value => 'success',
                SyncStatus::NotSynced->value => 'warning',
                SyncStatus::Manual->value => 'info',
            ]);
        yield DateTimeField::new('lastSyncedAt', 'Dernier import')->hideOnForm()->setFormat('dd/MM/yyyy HH:mm');
    }

    private function summarizeVariants(Product $product): string
    {
        $parts = array_map(static function (ProductVariant $variant): string {
            $price = null === $variant->getPriceCents() ? 'prix ?' : number_format($variant->getPriceCents() / 100, 2, ',', ' ').' €';
            $stock = $variant->isTrackInventory() ? 'stock '.$variant->getStock() : 'stock non suivi';

            return trim(($variant->getName() ?? '').' '.$price.' ('.$stock.')');
        }, $product->getVariants()->toArray());

        return implode(' · ', $parts);
    }
}
