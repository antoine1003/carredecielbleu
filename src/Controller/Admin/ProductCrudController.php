<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Enum\SyncStatus;
use App\Form\ProductImageType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
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
        // L'ordre des champs fixe aussi les colonnes de la liste : nom, visible, catégorie,
        // prix et stock, synchronisation, dernier import. Onglets, colonnes et fieldsets
        // ne s'appliquent qu'aux pages détail et formulaire.

        // ---------- Onglet 1 : ce que le site ajoute à SumUp ----------
        yield FormField::addTab('Sur le site', 'fa fa-globe');

        yield FormField::addColumn('col-12 col-xl-8');
        yield FormField::addFieldset('Fiche produit', 'fa fa-gem');
        yield TextField::new('name', 'Nom')
            ->setDisabled()
            ->setHelp('Vient de SumUp : modifiez-le dans SumUp, il sera mis à jour au prochain import.');
        yield TextEditorField::new('story', 'Histoire de la pièce')->hideOnIndex()
            ->setHelp('Texte affiché sous la description SumUp.');
        yield TextField::new('technique', 'Technique')->hideOnIndex()->setColumns('col-12 col-md-6');
        yield TextField::new('dimensions', 'Dimensions')->hideOnIndex()->setColumns('col-12 col-md-6');

        yield FormField::addColumn('col-12 col-xl-4');
        yield FormField::addFieldset('Publication', 'fa fa-eye');
        yield BooleanField::new('visible', 'Visible sur le site')->renderAsSwitch()
            ->setHelp('Un produit visible n\'apparaît que s\'il est disponible.');
        yield BooleanField::new('featured', 'Mis en avant sur l\'accueil')->renderAsSwitch()->hideOnIndex();
        yield ChoiceField::new('soldOutBehavior', 'Quand le stock est à zéro')->hideOnIndex()
            ->setFormTypeOption('placeholder', 'Réglage général du site')
            ->setRequired(false);

        yield FormField::addFieldset('Achat', 'fa fa-cart-shopping');
        yield UrlField::new('purchaseUrl', 'Lien d\'achat SumUp')->hideOnIndex()
            ->setHelp('Adresse de la fiche produit sur SumUp (https://…). Sans lien, le bouton devient « Me contacter ».');

        yield FormField::addFieldset('Adresse de la page', 'fa fa-link')->collapsible()->renderCollapsed();
        yield SlugField::new('slug', 'Adresse')->setTargetFieldName('name')->hideOnIndex()
            ->setUnlockConfirmationMessage('Changer l\'adresse casse les liens déjà partagés. Continuer ?')
            ->setHelp('Partie de l\'adresse après /boutique/.');

        // ---------- Onglet 2 : photos (plusieurs par produit) ----------
        yield FormField::addTab('Photos', 'fa fa-images')
            ->setHelp('La première photo est la photo principale (liste et partage sur les réseaux). Ajoutez-en autant que vous voulez : elles défilent sur la fiche produit.');
        yield CollectionField::new('images', false)
            ->onlyOnForms()
            ->setEntryType(ProductImageType::class)
            ->setEntryIsComplex()
            ->renderExpanded()
            ->allowAdd()
            ->allowDelete()
            ->setColumns('col-12')
            ->setFormTypeOption('by_reference', false)
            ->setFormTypeOption('entry_options', ['label' => false]);

        // ---------- Onglet 3 : données importées, en lecture seule ----------
        yield FormField::addTab('Données SumUp', 'fa fa-cash-register')
            ->setHelp('Remplacées à chaque import : modifiez-les dans SumUp.');

        yield FormField::addColumn('col-12 col-xl-8');
        yield FormField::addFieldset('Catalogue', 'fa fa-tags')
            ->setHelp($this->variantsHelp($pageName));
        yield AssociationField::new('category', 'Catégorie')->setDisabled();
        // Colonne calculée : adossée à l'id, mise en forme à partir des variantes.
        yield IntegerField::new('id', 'Prix et stock')
            ->hideOnForm()
            ->setSortable(false)
            ->formatValue(fn ($value, Product $product): string => $this->summarizeVariants($product));
        yield TextEditorField::new('description', 'Description SumUp')->onlyOnDetail();

        yield FormField::addFieldset('Référencement SumUp', 'fa fa-magnifying-glass')->hideOnForm();
        yield TextField::new('sumupSeoTitle', 'Titre')->onlyOnDetail();
        yield TextareaField::new('sumupSeoDescription', 'Description')->onlyOnDetail();

        yield FormField::addColumn('col-12 col-xl-4');
        yield FormField::addFieldset('Synchronisation', 'fa fa-rotate');
        yield ChoiceField::new('syncStatus', 'État')
            ->setDisabled()
            ->renderAsBadges([
                SyncStatus::Synced->value => 'success',
                SyncStatus::NotSynced->value => 'warning',
                SyncStatus::Manual->value => 'info',
            ]);
        yield DateTimeField::new('lastSyncedAt', 'Dernier import')->setDisabled()->setFormat('dd/MM/yyyy HH:mm');
        yield TextField::new('sku', 'SKU')->onlyOnDetail();
        yield TextField::new('sumupItemId', 'Identifiant SumUp')->onlyOnDetail();
    }

    /** Sur le formulaire, rappelle les prix et stocks importés (non modifiables ici). */
    private function variantsHelp(string $pageName): string
    {
        $product = $this->getContext()?->getEntity()->getInstance();
        if (Crud::PAGE_EDIT !== $pageName || !$product instanceof Product) {
            return '';
        }

        return 'Prix et stock : '.$this->summarizeVariants($product);
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
