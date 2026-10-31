<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AntiSpam\AltchaGuard;
use App\Service\Newsletter\NewsletterSubscriber;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\UX\Turbo\TurboBundle;

final class NewsletterController extends AbstractController
{
    /** Message affiché dans le bloc newsletter (clé de flash dédiée, sans JavaScript). */
    public const string FLASH = 'newsletter';

    private const array VARIANTS = ['banner', 'compact'];

    #[Route('/newsletter', name: 'app_newsletter_subscribe', methods: ['POST'])]
    public function subscribe(
        Request $request,
        NewsletterSubscriber $subscriber,
        ValidatorInterface $validator,
        AltchaGuard $altcha,
        #[Target('newsletter_subscription')]
        RateLimiterFactoryInterface $limiter,
    ): Response {
        $email = trim($request->request->getString('email'));
        [$type, $message] = $this->process($request, $email, $subscriber, $validator, $altcha, $limiter);

        // Avec JavaScript (Turbo) : seul le bloc newsletter est remplacé, la page ne se recharge pas.
        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);
            $variant = \in_array($request->request->getString('variant'), self::VARIANTS, true) ? $request->request->getString('variant') : 'banner';

            return $this->render('newsletter/subscribe.stream.html.twig', [
                'variant' => $variant,
                'message' => ['type' => $type, 'text' => $message],
                // En cas d'erreur, l'adresse saisie reste dans le champ pour être corrigée.
                'email' => 'error' === $type ? $email : '',
                'back' => $request->request->getString('back'),
            ]);
        }

        // Sans JavaScript : retour sur la page d'origine avec un message (303 après un POST).
        $this->addFlash(self::FLASH.'_'.$type, $message);

        return $this->redirect($this->backUrl($request), Response::HTTP_SEE_OTHER);
    }

    /**
     * @return array{'success'|'error', string}
     */
    private function process(
        Request $request,
        string $email,
        NewsletterSubscriber $subscriber,
        ValidatorInterface $validator,
        AltchaGuard $altcha,
        RateLimiterFactoryInterface $limiter,
    ): array {
        if (!$this->isCsrfTokenValid('newsletter', $request->request->getString('_token'))) {
            return ['error', 'Le formulaire a expiré, merci de réessayer.'];
        }
        // Champ piège rempli : on fait comme si tout allait bien, sans rien envoyer à Brevo.
        if ('' !== trim($request->request->getString('website'))) {
            return ['success', 'Merci, votre inscription est enregistrée.'];
        }
        if (!$altcha->verify($request->request->getString(AltchaGuard::FIELD))) {
            return ['error', 'La vérification anti-robots n\'a pas abouti. Patientez un instant qu\'elle se termine, puis réessayez.'];
        }
        if (\count($validator->validate($email, [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)])) > 0) {
            return ['error', 'Indiquez une adresse email complète, par exemple prenom@exemple.fr.'];
        }
        if (!$limiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
            return ['error', 'Trop d\'inscriptions envoyées en peu de temps depuis cette connexion. Merci de réessayer dans quelques minutes.'];
        }

        return $subscriber->subscribe($email)
            ? ['success', 'Merci, votre inscription est enregistrée : vous recevrez les prochaines nouvelles de l\'atelier.']
            : ['error', 'L\'inscription n\'a pas pu aboutir pour le moment. Merci de réessayer plus tard.'];
    }

    /** Retour sur la page d'origine (chemin interne uniquement), au niveau du bloc newsletter. */
    private function backUrl(Request $request): string
    {
        $back = $request->request->getString('back');

        return (str_starts_with($back, '/') && !str_starts_with($back, '//') ? $back : $this->generateUrl('app_home')).'#newsletter';
    }
}
