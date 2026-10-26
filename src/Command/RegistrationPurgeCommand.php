<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\RegistrationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * RGPD : supprime les inscriptions des ateliers passés depuis plus de N jours.
 * À lancer chaque jour par cron sur le serveur (voir README).
 */
#[AsCommand(name: 'app:registrations:purge', description: 'Supprime les inscriptions des ateliers passés (conservation limitée des données personnelles)')]
final readonly class RegistrationPurgeCommand
{
    public const int DEFAULT_RETENTION_DAYS = 90;

    public function __construct(
        private RegistrationRepository $registrations,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Nombre de jours de conservation après la date de l\'atelier')]
        int $days = self::DEFAULT_RETENTION_DAYS,
        #[Option(description: 'Affiche le nombre d\'inscriptions concernées sans rien supprimer', name: 'dry-run')]
        bool $dryRun = false,
    ): int {
        if ($days < 1) {
            $io->error('La durée de conservation doit être d\'au moins 1 jour.');

            return Command::INVALID;
        }

        $expired = $this->registrations->findExpired($this->clock->now()->modify(\sprintf('-%d days', $days)));

        if ($dryRun) {
            $io->note(\sprintf('%d inscription(s) seraient supprimées.', \count($expired)));

            return Command::SUCCESS;
        }

        foreach ($expired as $registration) {
            $this->entityManager->remove($registration);
        }
        $this->entityManager->flush();

        $io->success(\sprintf('%d inscription(s) supprimée(s) (ateliers passés depuis plus de %d jours).', \count($expired), $days));

        return Command::SUCCESS;
    }
}
