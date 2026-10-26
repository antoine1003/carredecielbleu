<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Registration;
use App\Entity\Workshop;
use App\Entity\WorkshopSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Registration>
 */
class RegistrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Registration::class);
    }

    public function findOneByCancelToken(string $token): ?Registration
    {
        if (1 !== preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        return $this->findOneBy(['cancelToken' => $token]);
    }

    public function countForSession(WorkshopSession $session): int
    {
        return $this->count(['session' => $session]);
    }

    /** @return list<Registration> */
    public function findForWorkshop(Workshop $workshop): array
    {
        /** @var list<Registration> */
        return $this->createQueryBuilder('r')
            ->join('r.session', 's')->addSelect('s')
            ->andWhere('s.workshop = :workshop')
            ->setParameter('workshop', $workshop)
            ->orderBy('r.lastName', 'ASC')
            ->addOrderBy('r.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Inscriptions dont la date d'atelier est passée depuis avant $before (purge RGPD).
     *
     * @return list<Registration>
     */
    public function findExpired(\DateTimeImmutable $before): array
    {
        /** @var list<Registration> */
        return $this->createQueryBuilder('r')
            ->join('r.session', 's')
            ->andWhere('s.startsAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->getResult();
    }
}
