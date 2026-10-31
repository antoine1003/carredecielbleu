<?php

declare(strict_types=1);

namespace App\Service\AntiSpam;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\CreateChallengeOptions;
use AltchaOrg\Altcha\VerifySolutionOptions;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Captcha ALTCHA auto-hébergé (preuve de travail, sans service tiers ni cookie).
 *
 * Le navigateur reçoit un défi signé (route app_altcha_challenge), le résout en arrière-plan
 * et joint la preuve au formulaire (champ « altcha ») ; le serveur la vérifie ici.
 * Clé de signature : variable d'environnement ALTCHA_HMAC_KEY (obligatoire en prod).
 */
final readonly class AltchaGuard
{
    /** Nom du champ ajouté au formulaire par le widget. */
    public const string FIELD = 'altcha';

    /** Difficulté : coût PBKDF2 et plage du compteur à retrouver (≈ 1 à 2 s sur un téléphone récent). */
    private const int COST = 5000;
    private const int COUNTER_MIN = 2000;
    private const int COUNTER_MAX = 5000;

    /** Durée de validité d'un défi : le temps de remplir un formulaire. */
    private const int TTL_SECONDS = 1800;

    public function __construct(
        #[Autowire('%env(ALTCHA_HMAC_KEY)%')]
        private string $hmacKey,
        #[Autowire(service: 'cache.app')]
        private CacheItemPoolInterface $cache,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string, mixed> défi au format attendu par le widget
     */
    public function createChallenge(): array
    {
        return $this->altcha()->createChallenge(new CreateChallengeOptions(
            algorithm: new Pbkdf2(),
            cost: self::COST,
            counter: random_int(self::COUNTER_MIN, self::COUNTER_MAX),
            expiresAt: $this->clock->now()->getTimestamp() + self::TTL_SECONDS,
        ))->toArray();
    }

    /**
     * Vérifie la preuve envoyée par le formulaire. Une preuve ne sert qu'une fois (anti-rejeu).
     */
    public function verify(?string $payload): bool
    {
        if (null === $payload || '' === trim($payload) || \strlen($payload) > 4096) {
            return false;
        }
        if ('' === trim($this->hmacKey)) {
            $this->logger->critical('ALTCHA_HMAC_KEY absente : tous les formulaires protégés sont refusés.');

            return false;
        }

        try {
            $result = $this->altcha()->verifySolution(new VerifySolutionOptions(
                algorithm: new Pbkdf2(),
                payload: $payload,
            ));
        } catch (\InvalidArgumentException|\JsonException) {
            return false;
        }

        if (!$result->verified) {
            return false;
        }

        // Anti-rejeu : on mémorise l'empreinte de la preuve jusqu'à l'expiration du défi.
        $item = $this->cache->getItem('altcha_used_'.hash('sha256', $payload));
        if ($item->isHit()) {
            return false;
        }
        $this->cache->save($item->set(true)->expiresAfter(self::TTL_SECONDS));

        return true;
    }

    private function altcha(): Altcha
    {
        return new Altcha(
            hmacSignatureSecret: $this->hmacKey,
            // Seconde clé dérivée de la première : active la vérification rapide côté serveur.
            hmacKeySignatureSecret: hash_hmac('sha256', 'altcha-key-signature', $this->hmacKey),
        );
    }
}
