<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ProductVariant;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Déclinaisons : uniquement le formulaire imbriqué dans celui du produit (pas de page à part).
 *
 * @extends AbstractCrudController<ProductVariant>
 */
final class ProductVariantCrudController extends AbstractCrudController
{
    public const string PAGE_IN_PRODUCT_NEW = 'new_in_product';
    public const string PAGE_IN_PRODUCT_EDIT = 'edit_in_product';

    public static function getEntityFqcn(): string
    {
        return ProductVariant::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('déclinaison')
            ->setEntityLabelInPlural('déclinaisons');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::INDEX, Action::NEW, Action::EDIT, Action::DETAIL, Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Modèle')->setColumns('col-12 col-md-6 col-xxl-4')
            ->setHelp('Ex. « épi bleu ». Vide pour un produit sans déclinaison.');
        yield MoneyField::new('priceCents', 'Prix')->setCurrency('EUR')->setStoredAsCents()
            ->setColumns('col-6 col-md-3 col-xxl-2')
            ->setHelp('Vide = prix sur demande');
        yield BooleanField::new('trackInventory', 'Suivre le stock')->renderAsSwitch()
            ->setColumns('col-6 col-md-3 col-xxl-3')
            ->setHelp('Non suivi = « Sur commande »');
        yield IntegerField::new('stock', 'Stock')->setColumns('col-6 col-md-3 col-xxl-3')
            ->setFormTypeOption('attr', ['min' => 0]);
    }
}
