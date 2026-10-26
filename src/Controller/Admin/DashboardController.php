<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        // Tableau de bord détaillé prévu en phase « admin finalisé » : on ouvre les produits.
        return $this->redirectToRoute('admin_product_index');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Carré de ciel bleu')
            // Contenu sur toute la largeur de l'écran (par défaut, EasyAdmin limite sa largeur).
            ->renderContentMaximized()
            ->setLocales(['fr']);
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::section('Boutique');
        yield MenuItem::linkTo(ProductCrudController::class, 'Produits', 'fa fa-gem');
        yield MenuItem::linkTo(CategoryCrudController::class, 'Catégories', 'fa fa-tags');
        yield MenuItem::linkToRoute('Importer le catalogue SumUp', 'fa fa-file-import', 'admin_catalog_import_index');

        yield MenuItem::section('Ateliers');
        yield MenuItem::linkTo(WorkshopCrudController::class, 'Ateliers', 'fa fa-palette');
        yield MenuItem::linkTo(WorkshopSessionCrudController::class, 'Dates', 'fa fa-calendar-days');
        yield MenuItem::linkTo(RegistrationCrudController::class, 'Inscriptions', 'fa fa-users');

        yield MenuItem::section('Site');
        yield MenuItem::linkTo(PageCrudController::class, 'Pages', 'fa fa-file-lines');
        yield MenuItem::linkToRoute('Réglages', 'fa fa-sliders', 'admin_site_setting_settings_edit');
        yield MenuItem::linkToUrl('Voir le site', 'fa fa-arrow-up-right-from-square', '/')->setLinkTarget('_blank');
    }
}
