<?php

declare(strict_types=1);

namespace App\Repository\Rental;

use App\Entity\Rental\Rent;
use App\Enum\RentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * RentRepository
 *
 * Package : Rental Management
 * Entité  : App\Entity\Rental\Rent
 *
 * @extends ServiceEntityRepository<Rent>
 */
class RentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rent::class);
    }

    public function save(Rent $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Rent $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByUuid(Uuid $uuid): ?Rent
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.uuid = :uuid')
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche l'échéance d'un bail pour une période donnée
     * (le couple lease/period est unique).
     */
    public function findOneByLeaseAndPeriod(int $leaseId, \DateTimeImmutable $period): ?Rent
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.lease = :leaseId')
            ->andWhere('r.period = :period')
            ->setParameter('leaseId', $leaseId)
            ->setParameter('period', $period)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste toutes les échéances d'un bail, triées par période.
     *
     * @return Rent[]
     */
    public function findByLease(int $leaseId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.lease = :leaseId')
            ->setParameter('leaseId', $leaseId)
            ->orderBy('r.period', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste les échéances en retard de paiement d'une organisation
     * (date d'échéance dépassée et statut non soldé).
     *
     * @return Rent[]
     */
    public function findOverdueByOrganization(int $organizationId): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.lease', 'l')
            ->andWhere('l.organization = :organizationId')
            ->andWhere('r.dueDate < :today')
            ->andWhere('r.status IN (:openStatuses)')
            ->setParameter('organizationId', $organizationId)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->setParameter('openStatuses', [RentStatus::PENDING, RentStatus::PARTIALLY_PAID, RentStatus::OVERDUE])
            ->orderBy('r.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
