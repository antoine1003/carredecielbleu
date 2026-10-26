<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Workshop;
use App\Enum\SessionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Workshop>
 */
class WorkshopRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Workshop::class);
    }

    /**
     * Ateliers publiés, avec leurs dates à venir non annulées (et leurs inscriptions pour les compteurs).
     *
     * @return list<Workshop>
     */
    public function findPublishedWithUpcomingSessions(\DateTimeImmutable $now): array
    {
        /** @var list<Workshop> */
        return $this->createQueryBuilder('w')
            ->leftJoin('w.sessions', 's', 'WITH', 's.startsAt >= :now AND s.status != :cancelled')->addSelect('s')
            ->leftJoin('s.registrations', 'r')->addSelect('r')
            ->andWhere('w.published = true')
            ->setParameter('now', $now)
            ->setParameter('cancelled', SessionStatus::Cancelled)
            ->orderBy('s.startsAt', 'ASC')
            ->addOrderBy('w.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOnePublishedBySlug(string $slug, \DateTimeImmutable $now): ?Workshop
    {
        /** @var Workshop|null */
        return $this->createQueryBuilder('w')
            ->leftJoin('w.sessions', 's', 'WITH', 's.startsAt >= :now AND s.status != :cancelled')->addSelect('s')
            ->leftJoin('s.registrations', 'r')->addSelect('r')
            ->andWhere('w.published = true')
            ->andWhere('w.slug = :slug')
            ->setParameter('slug', $slug)
            ->setParameter('now', $now)
            ->setParameter('cancelled', SessionStatus::Cancelled)
            ->orderBy('s.startsAt', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
