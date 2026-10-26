<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Page;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
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
        yield TextField::new('title', 'Titre');
        yield SlugField::new('slug', 'Adresse')->setTargetFieldName('title')
            ->setUnlockConfirmationMessage('Changer l\'adresse casse les liens déjà partagés. Continuer ?');
        yield BooleanField::new('published', 'Publiée')->renderAsSwitch();
        yield BooleanField::new('inFooter', 'Dans le pied de page')->renderAsSwitch();
        yield TextareaField::new('metaDescription', 'Description pour les moteurs de recherche')->hideOnIndex()
            ->setHelp('150 caractères environ, affichée sous le titre dans Google.')
            ->setMaxLength(300);
        yield TextEditorField::new('content', 'Contenu')->hideOnIndex();
        yield DateTimeField::new('updatedAt', 'Modifiée le')->hideOnForm()->setFormat('dd/MM/yyyy HH:mm');
    }
}
