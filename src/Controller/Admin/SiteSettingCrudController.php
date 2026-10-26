<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\SiteSetting;
use App\Repository\SiteSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Réglages du site : une seule ligne, uniquement en modification.
 *
 * @extends AbstractCrudController<SiteSetting>
 */
final class SiteSettingCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SiteSetting::class;
    }

    /** Ouvre le formulaire des réglages (crée la ligne la première fois). */
    #[AdminRoute('/reglages', name: 'settings_edit')]
    public function editSettings(SiteSettingRepository $repository, EntityManagerInterface $entityManager): RedirectResponse
    {
        $settings = $repository->findCurrent();
        if (null === $settings) {
            $settings = new SiteSetting();
            $entityManager->persist($settings);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_site_setting_edit', ['entityId' => $settings->getId()]);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('réglages')
            ->setEntityLabelInPlural('réglages')
            ->setPageTitle(Crud::PAGE_EDIT, 'Réglages du site');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE, Action::INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_RETURN);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Page d\'accueil');
        yield TextField::new('siteName', 'Nom du site');
        yield TextField::new('homeTitle', 'Grand titre');
        yield TextareaField::new('homeIntro', 'Texte de présentation');
        yield TextareaField::new('homeMetaDescription', 'Description pour les moteurs de recherche')
            ->setHelp('150 caractères environ.');

        yield FormField::addFieldset('Coordonnées');
        yield TextField::new('city', 'Ville');
        yield EmailField::new('contactEmail', 'Email de contact public')->setRequired(false);
        yield UrlField::new('instagramUrl', 'Instagram')->setRequired(false);
        yield UrlField::new('facebookUrl', 'Facebook')->setRequired(false);

        yield FormField::addFieldset('Boutique');
        yield ChoiceField::new('soldOutBehavior', 'Produits épuisés (stock suivi à zéro)')
            ->setHelp('Chaque produit peut avoir son propre réglage.');
    }
}
