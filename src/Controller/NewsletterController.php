<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AntiSpam\AltchaGuard;
use App\Service\Newsletter\NewsletterSubscriber;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class NewsletterController extends AbstractController
{
    /** Message affiché dans le bloc newsletter (clé de flash dédiée). */
    public const string FLASH = 'newsletter';

    #[Route('/newsletter', name: 'app_newsletter_subscribe', methods: ['POST'])]
    public function subscribe(
        Request $request,
        NewsletterSubscriber $subscriber,
        ValidatorInterface $validator,
        AltchaGuard $altcha,
        #[Target('newsletter_subscription')]
        RateLimiterFactoryInterface $limiter,
    ): RedirectResponse {
        $back = $this->backUrl($request);

        if (!$this->isCsrfTokenValid('newsletter', $request->request->getString('_token'))) {
            return $this->flashAndBack('error', 'Le formulaire a expiré, merci de réessayer.', $back);
        }
        // Champ piège rempli : on fait comme si tout allait bien, sans rien envoyer à Brevo.
        if ('' !== trim($request->request->getString('website'))) {
            return $this->flashAndBack('success', 'Merci, votre inscription est enregistrée.', $back);
        }

        if (!$altcha->verify($request->request->getString(AltchaGuard::FIELD))) {
            return $this->flashAndBack('error', 'La vérification anti-robots n\'a pas abouti. Patientez un instant qu\'elle se termine, puis réessayez.', $back);
        }

        $email = trim($request->request->getString('email'));
        if (\count($validator->validate($email, [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)])) > 0) {
            return $this->flashAndBack('error', 'Indiquez une adresse email complète, par exemple prenom@exemple.fr.', $back);
        }
        if (!$limiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
            return $this->flashAndBack('error', 'Trop d\'inscriptions envoyées en peu de temps depuis cette connexion. Merci de réessayer dans quelques minutes.', $back);
        }

        return $subscriber->subscribe($email)
            ? $this->flashAndBack('success', 'Merci, votre inscription est enregistrée : vous recevrez les prochaines nouvelles de l\'atelier.', $back)
            : $this->flashAndBack('error', 'L\'inscription n\'a pas pu aboutir pour le moment. Merci de réessayer plus tard.', $back);
    }

    /** Retour sur la page d'origine (chemin interne uniquement), au niveau du bloc newsletter. */
    private function backUrl(Request $request): string
    {
        $back = $request->request->getString('back');

        return (str_starts_with($back, '/') && !str_starts_with($back, '//') ? $back : $this->generateUrl('app_home')).'#newsletter';
    }

    private function flashAndBack(string $type, string $message, string $back): RedirectResponse
    {
        $this->addFlash(self::FLASH.'_'.$type, $message);

        // 303 : Turbo exige une redirection après un POST réussi.
        return $this->redirect($back, Response::HTTP_SEE_OTHER);
    }
}
