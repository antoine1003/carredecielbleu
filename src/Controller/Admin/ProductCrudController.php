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
 * Produits : tout est modifiable. Pour un produit synchronisé, nom, catégorie, description,
 * référencement, prix et stock sont remplacés au prochain import SumUp ; les champs propres
 * au site (photos, histoire, visibilité, lien d'achat…) ne le sont jamais.
 *
 * @extends AbstractCrudController<Product>
 */
final class ProductCrudController extends AbstractCrudController
{
    private const string SUMUP_HELP = 'Produit synchronisé : remplacé au prochain import SumUp (modifiez-le plutôt dans SumUp).';

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
            ->setPageTitle(Crud::PAGE_NEW, 'Nouveau produit')
            ->setPageTitle(Crud::PAGE_EDIT, static fn (Product $product): string => (string) $product->getName())
            ->setPageTitle(Crud::PAGE_DETAIL, static fn (Product $product): string => (string) $product->getName())
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name', 'sku', 'slug', 'category.name'])
            ->setPaginatorPageSize(50)
            ->setHelp(Crud::PAGE_INDEX, 'Les produits viennent de l\'import SumUp, ou sont créés ici à la main. Le site y ajoute ce que SumUp ne connaît pas : lien d\'achat, photos, histoire, visibilité.');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('category')
            ->add('visible')
            ->add('featured')
            ->add('syncStatus');
    }

    public function createEntity(string $entityFqcn): Product
    {
        // Produit saisi à la main : une déclinaison unique, stock non suivi (« Sur commande »).
        return (new Product())->addVariant((new ProductVariant())->setTrackInventory(false));
    }

    public function configureFields(string $pageName): iterable
    {
        // L'ordre des champs fixe aussi les colonnes de la liste : nom, visible, catégorie,
        // prix et stock, synchronisation, dernier import. Onglets, colonnes et fieldsets
        // ne s'appliquent qu'aux pages détail et formulaire.

        // ---------- Onglet 1 : fiche produit ----------
        yield FormField::addTab('Fiche produit', 'fa fa-gem');

        yield FormField::addColumn('col-12 col-xl-8');
        yield FormField::addFieldset('Présentation', 'fa fa-pen');
        yield TextField::new('name', 'Nom')->setHelp(self::SUMUP_HELP);
        yield BooleanField::new('visible', 'Visible sur le site')->renderAsSwitch()->onlyOnIndex();
        yield AssociationField::new('category', 'Catégorie')->setHelp(self::SUMUP_HELP);
        yield TextEditorField::new('description', 'Description')->hideOnIndex()->setHelp(self::SUMUP_HELP);
        yield TextEditorField::new('story', 'Histoire de la pièce')->hideOnIndex()
            ->setHelp('Texte affiché sous la description. Jamais modifié par l\'import.');
        yield TextField::new('technique', 'Technique')->hideOnIndex()->setColumns('col-12 col-md-6');
        yield TextField::new('dimensions', 'Dimensions')->hideOnIndex()->setColumns('col-12 col-md-6');

        yield FormField::addColumn('col-12 col-xl-4');
        yield FormField::addFieldset('Publication', 'fa fa-eye');
        yield BooleanField::new('visible', 'Visible sur le site')->renderAsSwitch()->hideOnIndex()
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

        // ---------- Onglet 2 : prix, stock et déclinaisons ----------
        yield FormField::addTab('Prix et déclinaisons', 'fa fa-tags')
            ->setHelp('Un produit simple a une seule ligne, sans nom de modèle. Avec plusieurs modèles, le site affiche « à partir de » le prix le plus bas. Les déclinaisons venant de SumUp sont remplacées à chaque import ; celles ajoutées ici restent telles quelles.');
        // Colonne calculée de la liste : adossée à l'id, mise en forme à partir des déclinaisons.
        yield IntegerField::new('id', 'Prix et stock')
            ->hideOnForm()
            ->setSortable(false)
            ->formatValue(fn ($value, Product $product): string => $this->summarizeVariants($product));
        yield CollectionField::new('variants', false)
            ->onlyOnForms()
            ->setColumns('col-12')
            ->useEntryCrudForm(
                ProductVariantCrudController::class,
                ProductVariantCrudController::PAGE_IN_PRODUCT_NEW,
                ProductVariantCrudController::PAGE_IN_PRODUCT_EDIT,
            )
            ->setEntryIsComplex()
            ->setEntryToStringMethod(static fn (ProductVariant $variant): string => ($variant->getName() ?? 'Modèle unique')
                .' — '.(SyncStatus::Manual === $variant->getSyncStatus() ? 'ajouté à la main' : 'SumUp, '.mb_strtolower($variant->getSyncStatus()->label())))
            ->renderExpanded()
            ->allowAdd()
            ->allowDelete()
            ->setFormTypeOption('by_reference', false);

        // ---------- Onglet 3 : photos (plusieurs par produit) ----------
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

        // ---------- Onglet 4 : référencement ----------
        yield FormField::addTab('Référencement', 'fa fa-magnifying-glass')
            ->setHelp('Ce qu\'affichent Google et les réseaux sociaux. '.self::SUMUP_HELP);
        yield TextField::new('sumupSeoTitle', 'Titre pour Google')->hideOnIndex()
            ->setHelp('Vide = nom du produit. Environ 60 caractères maximum.');
        yield TextareaField::new('sumupSeoDescription', 'Description pour Google')->hideOnIndex()
            ->setHelp('Vide = début de la description. Environ 160 caractères maximum.');

        // ---------- Onglet 5 : rattachement à SumUp ----------
        yield FormField::addTab('SumUp', 'fa fa-cash-register');

        yield FormField::addColumn('col-12 col-xl-6');
        yield FormField::addFieldset('Rattachement', 'fa fa-link');
        yield TextField::new('sku', 'SKU')->hideOnIndex()
            ->setHelp('Référence qui permet de retrouver le produit dans le fichier SumUp tant qu\'il n\'a pas d\'identifiant SumUp.');
        yield TextField::new('sumupItemId', 'Identifiant SumUp')->hideOnIndex()->hideWhenCreating()->setDisabled()
            ->setHelp('Renseigné par l\'import.');

        yield FormField::addColumn('col-12 col-xl-6');
        yield FormField::addFieldset('Synchronisation', 'fa fa-rotate');
        yield ChoiceField::new('syncStatus', 'État')
            ->setDisabled()
            ->hideWhenCreating()
            ->renderAsBadges([
                SyncStatus::Synced->value => 'success',
                SyncStatus::NotSynced->value => 'warning',
                SyncStatus::Manual->value => 'info',
            ]);
        yield DateTimeField::new('lastSyncedAt', 'Dernier import')->hideWhenCreating()->setDisabled()->setFormat('dd/MM/yyyy HH:mm');
        yield DateTimeField::new('createdAt', 'Créé le')->hideOnIndex()->hideWhenCreating()->setDisabled()->setFormat('dd/MM/yyyy HH:mm');
        yield DateTimeField::new('updatedAt', 'Modifié le')->hideOnIndex()->hideWhenCreating()->setDisabled()->setFormat('dd/MM/yyyy HH:mm');
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
