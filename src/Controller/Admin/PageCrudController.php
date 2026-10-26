<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Page;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<Page>
 */
final class PageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Page::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('page')
            ->setEntityLabelInPlural('pages')
            ->setPageTitle(Crud::PAGE_INDEX, 'Pages de contenu')
            ->setDefaultSort(['title' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addColumn('col-12 col-xl-8');
        yield FormField::addFieldset('Contenu', 'fa fa-pen');
        yield TextField::new('title', 'Titre');
        yield TextEditorField::new('content', 'Texte de la page')->hideOnIndex();

        yield FormField::addColumn('col-12 col-xl-4');
        yield FormField::addFieldset('Publication', 'fa fa-eye');
        yield BooleanField::new('published', 'Publiée')->renderAsSwitch();
        yield BooleanField::new('inFooter', 'Lien dans le pied de page')->renderAsSwitch();
        yield DateTimeField::new('updatedAt', 'Modifiée le')->hideOnForm()->setFormat('dd/MM/yyyy HH:mm');

        yield FormField::addFieldset('Référencement', 'fa fa-magnifying-glass');
        yield SlugField::new('slug', 'Adresse')->setTargetFieldName('title')
            ->setUnlockConfirmationMessage('Changer l\'adresse casse les liens déjà partagés. Continuer ?');
        yield TextareaField::new('metaDescription', 'Description pour les moteurs de recherche')->hideOnIndex()
            ->setHelp('150 caractères environ, affichée sous le titre dans Google.')
            ->setNumOfRows(4);
    }
}
