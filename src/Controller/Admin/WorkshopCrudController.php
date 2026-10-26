<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Registration;
use App\Entity\Workshop;
use App\Repository\RegistrationRepository;
use App\Service\Workshop\RegistrationCsvExporter;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractCrudController<Workshop>
 */
final class WorkshopCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Workshop::class;
    }

    /** Tableau dates × inscrits, avec confirmation de la date retenue. */
    #[AdminRoute('/{id}/planning', name: 'planning', options: ['methods' => ['GET']])]
    public function planning(#[MapEntity(id: 'id')] Workshop $workshop, RegistrationRepository $registrations): Response
    {
        $sessions = $workshop->getSessions()->toArray();

        // Une ligne par personne (regroupée par email), une colonne par date.
        $people = [];
        foreach ($registrations->findForWorkshop($workshop) as $registration) {
            $email = (string) $registration->getEmail();
            $people[$email] ??= ['registration' => $registration, 'sessions' => []];
            $people[$email]['sessions'][(int) $registration->getSession()?->getId()] = true;
        }

        return $this->render('admin/workshop/planning.html.twig', [
            'workshop' => $workshop,
            'sessions' => $sessions,
            'people' => array_values($people),
        ]);
    }

    /** Export CSV des inscrits de cet atelier. */
    #[AdminRoute('/{id}/export', name: 'export', options: ['methods' => ['GET']])]
    public function export(#[MapEntity(id: 'id')] Workshop $workshop, RegistrationRepository $registrations, RegistrationCsvExporter $exporter): Response
    {
        $rows = $registrations->findForWorkshop($workshop);
        usort($rows, static fn (Registration $a, Registration $b): int => [$a->getSession()?->getStartsAt(), $a->getLastName()] <=> [$b->getSession()?->getStartsAt(), $b->getLastName()]);

        return $exporter->response($rows, \sprintf('inscriptions-%s-%s.csv', $workshop->getSlug(), date('Y-m-d')));
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('atelier')
            ->setEntityLabelInPlural('ateliers')
            ->setPageTitle(Crud::PAGE_INDEX, 'Ateliers')
            ->setPageTitle(Crud::PAGE_EDIT, static fn (Workshop $w): string => (string) $w->getTitle())
            ->setPageTitle(Crud::PAGE_DETAIL, static fn (Workshop $w): string => (string) $w->getTitle())
            ->setDefaultSort(['title' => 'ASC'])
            ->setHelp(Crud::PAGE_INDEX, 'Un atelier est proposé à plusieurs dates. Ouvrez son tableau des inscrits pour confirmer la date retenue.');
    }

    public function configureActions(Actions $actions): Actions
    {
        $planning = Action::new('planning', 'Tableau des inscrits', 'fa fa-table-cells')
            ->linkToCrudAction('planning');

        return $actions
            ->add(Crud::PAGE_INDEX, $planning)
            ->add(Crud::PAGE_EDIT, $planning)
            ->add(Crud::PAGE_DETAIL, $planning)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addColumn('col-12 col-xl-8');
        yield FormField::addFieldset('Présentation', 'fa fa-palette');
        yield TextField::new('title', 'Titre');
        yield TextareaField::new('summary', 'Accroche')->hideOnIndex()
            ->setNumOfRows(2)
            ->setHelp('Une ou deux phrases affichées dans la liste des ateliers.');
        yield TextEditorField::new('description', 'Description')->hideOnIndex();

        yield FormField::addColumn('col-12 col-xl-4');
        yield FormField::addFieldset('Publication', 'fa fa-eye');
        yield BooleanField::new('published', 'Publié')->renderAsSwitch();
        yield SlugField::new('slug', 'Adresse')->setTargetFieldName('title')->hideOnIndex()
            ->setUnlockConfirmationMessage('Changer l\'adresse casse les liens déjà partagés. Continuer ?');

        yield FormField::addFieldset('Informations pratiques', 'fa fa-circle-info');
        yield TextField::new('duration', 'Durée')->hideOnIndex()->setHelp('Ex. : 3 heures');
        yield TextField::new('priceInfo', 'Tarif')->hideOnIndex()
            ->setHelp('Pour information uniquement : aucun paiement sur le site.');
        yield TextField::new('defaultLocation', 'Lieu par défaut')->hideOnIndex();
        yield IntegerField::new('defaultCapacity', 'Places par date')
            ->setHelp('Valeur par défaut, modifiable pour chaque date.');

        yield AssociationField::new('sessions', 'Dates')->onlyOnIndex();
    }
}
