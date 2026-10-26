<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Registration;
use App\Entity\Workshop;
use App\Entity\WorkshopSession;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Atelier de démonstration (JAMAIS en prod) : textes en placeholders explicites,
 * trois dates à venir et quelques inscriptions fictives (adresses en .invalid).
 */
final class WorkshopFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $workshop = (new Workshop())
            ->setTitle('Atelier découverte du vitrail (démo)')
            ->setSlug('atelier-decouverte-du-vitrail')
            ->setSummary('[Accroche de l\'atelier, une ou deux phrases à écrire par l\'artisane]')
            ->setDescription('<p>[Déroulé de l\'atelier, ce que l\'on fabrique, ce qu\'il faut apporter… Texte à écrire par l\'artisane.]</p>')
            ->setDuration('[Durée]')
            ->setPriceInfo('[Tarif à définir]')
            ->setDefaultLocation('[Adresse de l\'atelier]')
            ->setDefaultCapacity(6)
            ->setPublished(true);

        $dates = [];
        foreach (['+10 days', '+17 days', '+24 days'] as $offset) {
            $session = (new WorkshopSession())
                ->setStartsAt(new \DateTimeImmutable($offset.' 14:00'))
                ->setEndsAt(new \DateTimeImmutable($offset.' 17:00'));
            $workshop->addSession($session);
            $dates[] = $session;
        }
        $manager->persist($workshop);

        // [prénom, nom, dates choisies, prénom affiché en public]
        $people = [
            ['Camille', 'Démo', [0, 1], true],
            ['Louise', 'Exemple', [0], false],
            ['Jules', 'Fictif', [1, 2], true],
        ];
        foreach ($people as [$firstName, $lastName, $chosen, $public]) {
            $submissionId = bin2hex(random_bytes(16));
            foreach ($chosen as $index) {
                $registration = (new Registration($submissionId))
                    ->setFirstName($firstName)
                    ->setLastName($lastName)
                    ->setEmail(mb_strtolower($firstName).'@exemple.invalid')
                    ->setShowNamePublicly($public);
                $dates[$index]->addRegistration($registration);
                $manager->persist($registration);
            }
        }

        $manager->flush();
    }
}
