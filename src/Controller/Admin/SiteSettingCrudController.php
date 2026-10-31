<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\SiteSetting;
use App\Repository\SiteSettingRepository;
use App\Service\Newsletter\BrevoClient;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
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
    public function __construct(
        private readonly BrevoClient $brevo,
    ) {
    }

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
        yield FormField::addTab('Accueil', 'fa fa-house');
        yield TextField::new('siteName', 'Nom du site')->setColumns('col-12 col-xl-6');
        yield TextField::new('homeTitle', 'Grand titre')->setColumns('col-12 col-xl-6');
        yield TextareaField::new('homeIntro', 'Texte de présentation')->setNumOfRows(3)->setColumns('col-12 col-xl-6');
        yield TextareaField::new('homeMetaDescription', 'Description pour les moteurs de recherche')
            ->setNumOfRows(3)
            ->setColumns('col-12 col-xl-6')
            ->setHelp('150 caractères environ, affichée sous le titre dans Google.');

        yield FormField::addTab('Coordonnées', 'fa fa-address-card');
        yield TextField::new('city', 'Ville')->setColumns('col-12 col-md-6');
        yield EmailField::new('contactEmail', 'Email de contact')->setRequired(false)->setColumns('col-12 col-md-6');
        yield UrlField::new('instagramUrl', 'Instagram')->setRequired(false)->setColumns('col-12 col-md-6');
        yield UrlField::new('facebookUrl', 'Facebook')->setRequired(false)->setColumns('col-12 col-md-6');

        yield FormField::addTab('Boutique', 'fa fa-store');
        yield ChoiceField::new('soldOutBehavior', 'Produits épuisés (stock suivi à zéro)')
            ->setColumns('col-12 col-md-6')
            ->setHelp('Chaque produit peut avoir son propre réglage.');

        yield FormField::addTab('Newsletter', 'fa fa-envelope-open-text')
            ->setHelp($this->brevo->isConfigured()
                ? 'Clé API Brevo : configurée sur le serveur.'
                : 'Clé API Brevo : ABSENTE. Ajoutez BREVO_API_KEY dans les variables d\'environnement du serveur (ou .env.local) : tant qu\'elle manque, le formulaire d\'inscription reste masqué.');
        yield BooleanField::new('newsletterEnabled', 'Proposer l\'inscription à la newsletter')->renderAsSwitch()
            ->setColumns('col-12');
        yield IntegerField::new('newsletterListId', 'Identifiant de la liste Brevo')
            ->setColumns('col-12 col-md-6')
            ->setRequired(false)
            ->setHelp('Dans Brevo : Contacts › Listes, le numéro (ID) de la liste qui reçoit les inscriptions.');
        yield TextField::new('newsletterTitle', 'Titre du bandeau')->setColumns('col-12 col-md-6');
        yield TextareaField::new('newsletterText', 'Texte du bandeau')->setNumOfRows(2)->setColumns('col-12');

        yield FormField::addTab('Ateliers', 'fa fa-palette')
            ->setHelp('Règles de l\'acompte, communes à tous les ateliers. Le montant et le lien de paiement SumUp se règlent sur chaque atelier.');
        yield IntegerField::new('depositPaymentDays', 'Délai pour payer l\'acompte (jours)')->setColumns('col-12 col-md-6')
            ->setHelp('Compté à partir de la confirmation de la date. Ex. : 5');
        yield IntegerField::new('refundDaysBefore', 'Annulation remboursée jusqu\'à (jours avant l\'atelier)')->setColumns('col-12 col-md-6')
            ->setHelp('Ex. : 7 = acompte remboursé si la personne annule au moins 7 jours avant ; ensuite, il reste acquis.');
        yield TextareaField::new('cancellationTerms', 'Conditions complémentaires')->setNumOfRows(3)->setColumns('col-12')
            ->setRequired(false)
            ->setHelp('Affichées avec la règle de remboursement (page de l\'atelier et emails). Faites-les relire si besoin (comptable, CMA).');
    }
}
