<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Category;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<Category>
 */
final class CategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Category::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('catégorie')
            ->setEntityLabelInPlural('catégories')
            ->setPageTitle(Crud::PAGE_INDEX, 'Catégories')
            ->setDefaultSort(['position' => 'ASC', 'name' => 'ASC'])
            ->setHelp(Crud::PAGE_INDEX, 'Créées par l\'import SumUp. Vous pouvez renommer une catégorie : l\'import la reconnaît par son nom SumUp.');
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom affiché');
        yield TextField::new('sumupName', 'Nom dans SumUp')->setDisabled()->hideWhenCreating();
        yield SlugField::new('slug', 'Adresse')->setTargetFieldName('name')->hideOnIndex()
            ->setUnlockConfirmationMessage('Changer l\'adresse casse les liens déjà partagés. Continuer ?');
        yield BooleanField::new('visible', 'Proposée en filtre')->renderAsSwitch();
        yield IntegerField::new('position', 'Ordre')->setHelp('Plus petit = affiché en premier.');
        yield TextareaField::new('description', 'Description')->hideOnIndex();
    }
}
