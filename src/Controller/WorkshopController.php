<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\WorkshopSession;
use App\Form\RegistrationType;
use App\Repository\RegistrationRepository;
use App\Repository\WorkshopRepository;
use App\Service\AntiSpam\AltchaGuard;
use App\Service\Workshop\RegistrationRequest;
use App\Service\Workshop\RegistrationService;
use App\Service\Workshop\SessionAvailability;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/ateliers')]
final class WorkshopController extends AbstractController
{
    public function __construct(
        private readonly WorkshopRepository $workshops,
        private readonly SessionAvailability $availability,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: 'app_workshops', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('workshop/index.html.twig', [
            'workshops' => $this->workshops->findPublishedWithUpcomingSessions($this->clock->now()),
        ]);
    }

    #[Route('/{slug}', name: 'app_workshop', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET', 'POST'])]
    public function show(
        string $slug,
        Request $request,
        RegistrationService $registrations,
        AltchaGuard $altcha,
        #[Target('workshop_registration')]
        RateLimiterFactoryInterface $limiter,
    ): Response {
        $workshop = $this->workshops->findOnePublishedBySlug($slug, $this->clock->now())
            ?? throw $this->createNotFoundException('Atelier introuvable.');

        $openSessions = array_values(array_filter(
            $workshop->getSessions()->toArray(),
            fn (WorkshopSession $s): bool => $this->availability->acceptsRegistrations($s),
        ));

        $data = new RegistrationRequest();
        $form = $this->createForm(RegistrationType::class, $data, ['sessions' => $openSessions]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Piège à robots rempli : on fait comme si tout allait bien, sans rien enregistrer.
            if ('' !== trim((string) $form->get(RegistrationType::HONEYPOT)->getData())) {
                return $this->redirectToRoute('app_workshop', ['slug' => $slug, 'inscription' => 'ok'], Response::HTTP_SEE_OTHER);
            }

            // Captcha ALTCHA : preuve absente, invalide ou déjà utilisée → formulaire refusé.
            if (!$altcha->verify($request->request->getString(AltchaGuard::FIELD))) {
                $form->addError(new FormError('La vérification anti-robots n\'a pas abouti. Patientez un instant qu\'elle se termine, puis renvoyez le formulaire.'));
            }

            if ($form->isValid()) {
                // Seules les inscriptions valides comptent : des erreurs de saisie ne bloquent personne.
                if (!$limiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
                    $this->addFlash('error', 'Trop d\'inscriptions envoyées en peu de temps depuis cette connexion. Merci de réessayer dans quelques minutes.');

                    return $this->redirectToRoute('app_workshop', ['slug' => $slug], Response::HTTP_SEE_OTHER);
                }

                $result = $registrations->register($workshop, $data);
                $request->getSession()->set('workshop_registration_result', [
                    'created' => \count($result->created),
                    'existing' => \count($result->existing),
                    'unavailable' => array_map(static fn (WorkshopSession $s): string => (string) $s->getStartsAt()?->format(\DATE_ATOM), $result->unavailable),
                    'emailSent' => $result->emailSent,
                ]);

                return $this->redirectToRoute('app_workshop', ['slug' => $slug, 'inscription' => 'ok'], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('workshop/show.html.twig', [
            'workshop' => $workshop,
            'form' => $form,
            'result' => 'ok' === $request->query->get('inscription')
                ? ($request->getSession()->remove('workshop_registration_result') ?? ['created' => 1, 'existing' => 0, 'unavailable' => [], 'emailSent' => null])
                : null,
            // Formulaire soumis mais invalide (le cas valide a redirigé plus haut) : 422 pour Turbo.
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /** Page de confirmation d'annulation (GET sans effet : les clients mail pré-chargent parfois les liens). */
    #[Route('/annulation/{token}', name: 'app_workshop_cancel', requirements: ['token' => '[a-f0-9]{64}'], methods: ['GET', 'POST'])]
    public function cancel(string $token, Request $request, RegistrationRepository $repository, RegistrationService $registrations): Response
    {
        $registration = $repository->findOneByCancelToken($token);
        if (null === $registration) {
            return $this->render('workshop/cancel.html.twig', ['registration' => null], new Response(status: Response::HTTP_NOT_FOUND));
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('workshop_cancel'.$token, $request->request->getString('_token'))) {
                $this->addFlash('error', 'Le formulaire a expiré, merci de réessayer.');

                return $this->redirectToRoute('app_workshop_cancel', ['token' => $token]);
            }
            $session = $registrations->cancel($registration);
            $this->addFlash('success', \sprintf(
                'Votre inscription à l\'atelier « %s » du %s est annulée, et vos coordonnées pour cette date sont supprimées. Merci de m\'avoir prévenue.',
                $session->getWorkshop()?->getTitle(),
                $session->getStartsAt()?->format('d/m/Y à H:i'),
            ));

            // Redirection après POST (Turbo l'exige, et un rechargement ne renvoie pas le formulaire).
            return $this->redirectToRoute('app_workshops', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('workshop/cancel.html.twig', ['registration' => $registration]);
    }
}
