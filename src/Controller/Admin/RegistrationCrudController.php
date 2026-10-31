<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Registration;
use App\Repository\RegistrationRepository;
use App\Service\Workshop\RegistrationCsvExporter;
use App\Service\Workshop\WorkshopMailer;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inscriptions : consultation, correction, suppression (RGPD) et export CSV.
 *
 * @extends AbstractCrudController<Registration>
 */
final class RegistrationCrudController extends AbstractCrudController
{
    /** Coche ou décoche « Acompte reçu » (bouton du suivi de l'atelier). */
    #[AdminRoute('/{id}/acompte', name: 'deposit', options: ['methods' => ['POST']])]
    public function toggleDeposit(#[MapEntity(id: 'id')] Registration $registration, Request $request, EntityManagerInterface $entityManager, ClockInterface $clock, WorkshopMailer $mailer): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('deposit'.$registration->getId(), $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Le formulaire a expiré, merci de recommencer.');
        } else {
            $registration->setDepositPaidAt($registration->isDepositPaid() ? null : $clock->now());
            $entityManager->flush();
            $name = $registration->getFirstName().' '.$registration->getLastName();

            if (!$registration->isDepositPaid()) {
                $this->addFlash('success', \sprintf('Acompte de %s remis « en attente ».', $name));
            } elseif ($mailer->sendDepositReceived($registration)) {
                $this->addFlash('success', \sprintf('Acompte de %s noté comme reçu. Un email lui confirme que son inscription est validée.', $name));
            } else {
                $this->addFlash('warning', \sprintf('Acompte de %s noté comme reçu, mais l\'email de confirmation n\'a pas pu être envoyé : prévenez-la directement.', $name));
            }
        }

        return $this->redirectToRoute('admin_workshop_planning', ['id' => $registration->getSession()?->getWorkshop()?->getId()]);
    }

    public static function getEntityFqcn(): string
    {
        return Registration::class;
    }

    /** Export CSV de toutes les inscriptions. */
    #[AdminRoute('/export', name: 'export', options: ['methods' => ['GET']])]
    public function export(RegistrationRepository $registrations, RegistrationCsvExporter $exporter): Response
    {
        $rows = $registrations->createQueryBuilder('r')
            ->join('r.session', 's')->addSelect('s')
            ->join('s.workshop', 'w')->addSelect('w')
            ->orderBy('w.title', 'ASC')->addOrderBy('s.startsAt', 'ASC')->addOrderBy('r.lastName', 'ASC')
            ->getQuery()->toIterable();

        /** @var iterable<Registration> $rows */
        return $exporter->response($rows, \sprintf('inscriptions-%s.csv', date('Y-m-d')));
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('inscription')
            ->setEntityLabelInPlural('inscriptions')
            ->setPageTitle(Crud::PAGE_INDEX, 'Inscriptions')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['firstName', 'lastName', 'email', 'session.workshop.title'])
            ->setHelp(Crud::PAGE_INDEX, 'Données personnelles : supprimez une inscription à la demande de la personne. Les inscriptions sont purgées automatiquement après l\'atelier.');
    }

    public function configureActions(Actions $actions): Actions
    {
        $export = Action::new('export', 'Exporter en CSV', 'fa fa-file-csv')
            ->linkToCrudAction('export')
            ->createAsGlobalAction();

        return $actions->add(Crud::PAGE_INDEX, $export);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('session')->add('email')->add('createdAt');
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addColumn('col-12 col-xl-8');
        yield FormField::addFieldset('Personne', 'fa fa-user');
        yield TextField::new('firstName', 'Prénom')->setColumns('col-12 col-md-6');
        yield TextField::new('lastName', 'Nom')->setColumns('col-12 col-md-6');
        yield EmailField::new('email', 'Email');
        yield TelephoneField::new('phone', 'Téléphone')->hideOnIndex();

        yield FormField::addColumn('col-12 col-xl-4');
        yield FormField::addFieldset('Atelier', 'fa fa-calendar');
        yield AssociationField::new('session', 'Date');
        yield BooleanField::new('showNamePublicly', 'Prénom affiché en public')->renderAsSwitch(false)
            ->setHelp('Uniquement avec l\'accord de la personne.');
        yield DateTimeField::new('createdAt', 'Inscrit le')->hideOnForm()->setFormat('dd/MM/yyyy HH:mm');

        yield FormField::addFieldset('Acompte', 'fa fa-euro-sign');
        yield DateTimeField::new('depositPaidAt', 'Acompte reçu le')->setFormat('dd/MM/yyyy')->setRequired(false)
            ->setHelp('Vide = pas encore reçu. Le plus simple : la case « Acompte reçu » du suivi de l\'atelier.');
        yield DateTimeField::new('termsAcceptedAt', 'Conditions d\'annulation acceptées le')->hideOnIndex()
            ->setDisabled()->setFormat('dd/MM/yyyy HH:mm');
    }
}
