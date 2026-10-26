<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\WorkshopSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkshopSession>
 */
class WorkshopSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkshopSession::class);
    }
}
