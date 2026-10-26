<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\WorkshopSession;
use App\Enum\SessionStatus;
use App\Service\Workshop\SessionConfirmationService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @extends AbstractCrudController<WorkshopSession>
 */
final class WorkshopSessionCrudController extends AbstractCrudController
{
    /** Pages du formulaire compact utilisé dans le formulaire de l'atelier. */
    public const string PAGE_IN_WORKSHOP_NEW = 'new_in_workshop';
    public const string PAGE_IN_WORKSHOP_EDIT = 'edit_in_workshop';

    public static function getEntityFqcn(): string
    {
        return WorkshopSession::class;
    }

    /** Confirme la date retenue et envoie les emails (depuis le tableau des inscrits). */
    #[AdminRoute('/{id}/confirmer', name: 'confirm', options: ['methods' => ['POST']])]
    public function confirm(#[MapEntity(id: 'id')] WorkshopSession $session, Request $request, SessionConfirmationService $confirmation): RedirectResponse
    {
        $workshopId = $session->getWorkshop()?->getId();

        if (!$this->isCsrfTokenValid('confirm_session'.$session->getId(), $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Le formulaire a expiré, merci de recommencer.');
        } else {
            try {
                $result = $confirmation->confirm($session, $request->request->getBoolean('cancel_others'));
                $this->addFlash('success', $result->summary());
                if ($result->emailsFailed > 0) {
                    $this->addFlash('warning', \sprintf('%d email(s) n\'ont pas pu être envoyés : vérifiez la configuration d\'envoi et prévenez ces personnes.', $result->emailsFailed));
                }
            } catch (\DomainException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_workshop_planning', ['id' => $workshopId]);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('date d\'atelier')
            ->setEntityLabelInPlural('dates d\'ateliers')
            ->setPageTitle(Crud::PAGE_INDEX, 'Dates d\'ateliers')
            ->setDefaultSort(['startsAt' => 'ASC'])
            ->setHelp(Crud::PAGE_INDEX, 'Pour confirmer une date et prévenir les inscrits, passez par le tableau des inscrits de l\'atelier.');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('workshop')->add('status')->add('startsAt');
    }

    public function configureFields(string $pageName): iterable
    {
        // Formulaire compact d'une date, imbriqué dans le formulaire de l'atelier.
        if (self::PAGE_IN_WORKSHOP_NEW === $pageName || self::PAGE_IN_WORKSHOP_EDIT === $pageName) {
            yield DateTimeField::new('startsAt', 'Début')->setColumns('col-12 col-md-6');
            yield DateTimeField::new('endsAt', 'Fin')->setColumns('col-12 col-md-6');
            yield TextField::new('location', 'Lieu')->setColumns('col-12 col-md-6')
                ->setHelp('Vide = lieu par défaut de l\'atelier');
            yield IntegerField::new('capacity', 'Places')->setColumns('col-12 col-md-6')
                ->setHelp('Vide = valeur de l\'atelier');
            if (self::PAGE_IN_WORKSHOP_EDIT === $pageName) {
                yield ChoiceField::new('status', 'Statut')->setColumns('col-12 col-md-6');
            }

            return;
        }

        yield FormField::addColumn('col-12 col-xl-8');
        yield FormField::addFieldset('Date', 'fa fa-calendar');
        yield AssociationField::new('workshop', 'Atelier');
        yield DateTimeField::new('startsAt', 'Début')->setFormat('EEE dd/MM/yyyy HH:mm')->setColumns('col-12 col-md-6');
        yield DateTimeField::new('endsAt', 'Fin')->setFormat('HH:mm')->hideOnIndex()->setColumns('col-12 col-md-6');
        yield TextField::new('location', 'Lieu')->hideOnIndex()
            ->setHelp('Laisser vide pour utiliser le lieu par défaut de l\'atelier.');

        yield FormField::addColumn('col-12 col-xl-4');
        yield FormField::addFieldset('Inscriptions', 'fa fa-users');
        yield ChoiceField::new('status', 'Statut')
            ->renderAsBadges([
                SessionStatus::Open->value => 'info',
                SessionStatus::Confirmed->value => 'success',
                SessionStatus::Full->value => 'warning',
                SessionStatus::Cancelled->value => 'secondary',
            ])
            ->setHelp('« Confirmée » s\'obtient depuis le tableau des inscrits (envoi des emails).');
        yield IntegerField::new('capacity', 'Places')->hideOnIndex()
            ->setHelp('Laisser vide pour utiliser la valeur de l\'atelier.');
        yield AssociationField::new('registrations', 'Inscrits')->hideOnForm();
    }
}
