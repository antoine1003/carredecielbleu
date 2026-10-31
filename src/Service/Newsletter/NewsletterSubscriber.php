<?php

declare(strict_types=1);

namespace App\Service\Newsletter;

use App\Service\SiteSettingsProvider;
use Psr\Log\LoggerInterface;

/**
 * Inscription d'une adresse email à la newsletter (liste Brevo choisie dans les réglages du site).
 * Le site ne conserve pas l'adresse : elle part directement chez Brevo.
 */
final readonly class NewsletterSubscriber
{
    public function __construct(
        private BrevoClient $brevo,
        private SiteSettingsProvider $settings,
        private LoggerInterface $logger,
    ) {
    }

    /** Le bandeau d'inscription s'affiche : activée dans l'admin, liste choisie, clé API présente. */
    public function isAvailable(): bool
    {
        $settings = $this->settings->get();

        return $settings->isNewsletterEnabled()
            && null !== $settings->getNewsletterListId()
            && $this->brevo->isConfigured();
    }

    /**
     * @return bool true si l'inscription a réussi
     */
    public function subscribe(string $email): bool
    {
        $listId = $this->settings->get()->getNewsletterListId();
        if (!$this->isAvailable() || null === $listId) {
            return false;
        }

        try {
            $this->brevo->addContactToList(mb_strtolower(trim($email)), $listId);

            return true;
        } catch (NewsletterException $e) {
            // Pas l'adresse en clair dans les logs (RGPD).
            $this->logger->error('Inscription newsletter impossible : {message}', ['message' => $e->getMessage()]);

            return false;
        }
    }
}
