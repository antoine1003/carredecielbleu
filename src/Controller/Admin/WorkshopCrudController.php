<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Registration;
use App\Entity\Workshop;
use App\Entity\WorkshopImage;
use App\Entity\WorkshopSession;
use App\Form\ResponsiveImageType;
use App\Repository\RegistrationRepository;
use App\Service\Workshop\RegistrationCsvExporter;
use App\Service\Workshop\WorkshopOverview;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
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

    /**
     * Suivi d'un atelier : où en est-on, les dates en un coup d'œil (inscrits, places),
     * confirmation de la date retenue, et tableau détaillé personnes × dates.
     */
    #[AdminRoute('/{id}/suivi', name: 'planning', options: ['methods' => ['GET']])]
    public function planning(#[MapEntity(id: 'id')] Workshop $workshop, RegistrationRepository $registrations, WorkshopOverview $overview): Response
    {
        $sessions = $workshop->getSessions()->toArray();

        // Tableau détaillé : une ligne par personne (regroupée par email), une colonne par date.
        $people = [];
        foreach ($registrations->findForWorkshop($workshop) as $registration) {
            $email = (string) $registration->getEmail();
            $people[$email] ??= ['registration' => $registration, 'sessions' => []];
            $people[$email]['sessions'][(int) $registration->getSession()?->getId()] = true;
        }

        return $this->render('admin/workshop/planning.html.twig', [
            'workshop' => $workshop,
            'summary' => $overview->of($workshop),
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
            ->setHelp(Crud::PAGE_INDEX, 'Cliquez sur un atelier pour voir où il en est : dates proposées, inscrits, et choix de la date retenue.');
    }

    public function configureActions(Actions $actions): Actions
    {
        $planning = Action::new('planning', 'Suivi de l\'atelier', 'fa fa-calendar-check')
            ->linkToCrudAction('planning');

        // Le suivi remplace la page « détail » : c'est là qu'on voit tout l'atelier.
        return $actions
            ->add(Crud::PAGE_INDEX, $planning)
            ->add(Crud::PAGE_EDIT, $planning)
            ->reorder(Crud::PAGE_INDEX, ['planning', Action::EDIT, Action::DELETE]);
    }

    public function configureFields(string $pageName): iterable
    {
        // L'ordre des champs fixe aussi les colonnes de la liste : titre (lien vers le suivi), publié, situation.

        // ---------- Onglet 1 : textes et publication ----------
        yield FormField::addTab('Présentation', 'fa fa-palette');
        yield FormField::addColumn('col-12 col-xl-8');
        yield TextField::new('title', 'Titre')
            ->setTemplatePath('admin/workshop/_index_title.html.twig');
        yield TextareaField::new('summary', 'Accroche')->hideOnIndex()
            ->setNumOfRows(2)
            ->setHelp('Une ou deux phrases affichées dans la liste des ateliers.');
        yield TextEditorField::new('description', 'Description')->hideOnIndex();

        yield FormField::addColumn('col-12 col-xl-4');
        yield FormField::addFieldset('Publication', 'fa fa-eye');
        yield BooleanField::new('published', 'Publié')->renderAsSwitch();
        yield SlugField::new('slug', 'Adresse')->setTargetFieldName('title')->hideOnIndex()
            ->setUnlockConfirmationMessage('Changer l\'adresse casse les liens déjà partagés. Continuer ?');

        // ---------- Onglet 2 : informations pratiques ----------
        yield FormField::addTab('Infos pratiques', 'fa fa-circle-info');
        yield TextField::new('duration', 'Durée')->hideOnIndex()->setColumns('col-12 col-md-6')
            ->setHelp('Ex. : 3 heures');
        yield TextField::new('priceInfo', 'Tarif')->hideOnIndex()->setColumns('col-12 col-md-6')
            ->setHelp('Pour information uniquement : aucun paiement sur le site.');
        yield TextField::new('defaultLocation', 'Lieu par défaut')->hideOnIndex()->setColumns('col-12 col-md-6')
            ->setHelp('Utilisé pour toutes les dates qui n\'ont pas de lieu propre.');
        yield IntegerField::new('defaultCapacity', 'Places par date')->hideOnIndex()->setColumns('col-12 col-md-6')
            ->setHelp('Valeur par défaut, modifiable pour chaque date.');

        // Situation de l'atelier (colonne de la liste) : date retenue, date à choisir, inscrits…
        yield IntegerField::new('id', 'Situation')->onlyOnIndex()->setSortable(false)
            ->setTemplatePath('admin/workshop/_index_status.html.twig');

        // ---------- Onglet 3 : photos et vidéo ----------
        yield FormField::addTab('Photos et vidéo', 'fa fa-images');
        yield FormField::addFieldset('Photos', 'fa fa-camera')
            ->setHelp('Ce qui sera réalisé, le lieu, l\'ambiance… La première photo est la photo principale. Elles défilent sur la page de l\'atelier.');
        yield CollectionField::new('images', false)
            ->onlyOnForms()
            ->setEntryType(ResponsiveImageType::class)
            ->setEntryIsComplex()
            ->renderExpanded()
            ->allowAdd()
            ->allowDelete()
            ->setColumns('col-12')
            ->setFormTypeOption('by_reference', false)
            ->setFormTypeOption('entry_options', ['label' => false, 'data_class' => WorkshopImage::class, 'alt_fallback' => 'le titre de l\'atelier']);
        yield FormField::addFieldset('Vidéo', 'fa fa-video');
        yield UrlField::new('videoUrl', 'Lien vers une vidéo')->hideOnIndex()->setColumns('col-12 col-lg-8')
            ->setHelp('Lien YouTube (la vidéo s\'affiche dans la page, chargée seulement quand le visiteur clique) ou lien vers une autre page (affiché comme un simple lien).');

        // ---------- Onglet 4 : dates proposées ----------
        yield FormField::addTab('Dates', 'fa fa-calendar-days')
            ->setHelp('Ajoutez autant de dates que vous voulez. Une date qui a déjà des inscrits ne peut pas être retirée ici : passez-la en « Annulée ».');
        yield CollectionField::new('sessions', false)
            ->onlyOnForms()
            ->setColumns('col-12')
            ->useEntryCrudForm(
                WorkshopSessionCrudController::class,
                WorkshopSessionCrudController::PAGE_IN_WORKSHOP_NEW,
                WorkshopSessionCrudController::PAGE_IN_WORKSHOP_EDIT,
            )
            ->setEntryIsComplex()
            ->setEntryToStringMethod(static fn (WorkshopSession $session): string => null === $session->getStartsAt()
                ? 'Nouvelle date'
                : ucfirst((string) \IntlDateFormatter::formatObject($session->getStartsAt(), 'EEEE d MMMM y, HH:mm', 'fr')).' — '.$session->getStatus()->label().' — '.$session->getRegistrations()->count().' inscrit(s)')
            ->renderExpanded()
            ->allowAdd()
            ->allowDelete()
            ->setFormTypeOption('by_reference', false);
    }

    /**
     * Retirer une date de la liste la supprimerait avec ses inscriptions : on refuse si elle a des inscrits.
     */
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance->getSessions() instanceof PersistentCollection) {
            foreach ($entityInstance->getSessions()->getDeleteDiff() as $removed) {
                if ($removed->getRegistrations()->count() > 0) {
                    $entityInstance->addSession($removed);
                    $this->addFlash('warning', \sprintf(
                        'La date du %s a %d inscrit(s) : elle n\'a pas été supprimée. Passez-la en « Annulée » pour ne plus la proposer.',
                        $removed->getStartsAt()?->format('d/m/Y H:i'),
                        $removed->getRegistrations()->count(),
                    ));
                }
            }
        }

        parent::updateEntity($entityManager, $entityInstance);
    }
}
