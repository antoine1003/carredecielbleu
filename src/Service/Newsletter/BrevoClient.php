<?php

declare(strict_types=1);

namespace App\Service\Newsletter;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Accès à l'API Brevo (v3) pour les contacts.
 * La clé API vient de la variable d'environnement BREVO_API_KEY (jamais de la base ni de l'admin).
 *
 * @see https://developers.brevo.com/reference/createcontact
 */
final readonly class BrevoClient
{
    private const float TIMEOUT_SECONDS = 8.0;

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(BREVO_API_KEY)%')]
        private string $apiKey,
        #[Autowire('%env(BREVO_API_URL)%')]
        private string $apiUrl,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->apiKey);
    }

    /**
     * Crée le contact, ou le met à jour s'il existe déjà, et l'ajoute à la liste.
     *
     * @throws NewsletterException
     */
    public function addContactToList(string $email, int $listId): void
    {
        if (!$this->isConfigured()) {
            throw new NewsletterException('Clé API Brevo absente (BREVO_API_KEY).');
        }

        try {
            $response = $this->httpClient->request('POST', rtrim($this->apiUrl, '/').'/contacts', [
                'headers' => [
                    'api-key' => $this->apiKey,
                    'accept' => 'application/json',
                ],
                'json' => [
                    'email' => $email,
                    'listIds' => [$listId],
                    // Contact déjà connu de Brevo : on l'ajoute à la liste au lieu d'échouer.
                    'updateEnabled' => true,
                ],
                'timeout' => self::TIMEOUT_SECONDS,
            ]);
            $status = $response->getStatusCode();

            // 201 : contact créé ; 204 : contact existant mis à jour. Tout autre 2xx est accepté.
            if ($status < 200 || $status >= 300) {
                $body = $response->toArray(false);

                throw new NewsletterException(\sprintf('Brevo a répondu %d : %s', $status, \is_string($body['message'] ?? null) ? $body['message'] : 'erreur inconnue'));
            }
        } catch (ExceptionInterface $e) {
            throw new NewsletterException('Brevo injoignable : '.$e->getMessage(), previous: $e);
        }
    }
}
