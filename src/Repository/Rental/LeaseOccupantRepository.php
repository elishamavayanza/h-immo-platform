<?php

declare(strict_types=1);

namespace App\Repository\Rental;

use App\Entity\Rental\Lease;
use App\Entity\Rental\LeaseOccupant;
use App\Entity\Rental\Tenant;
use App\Repository\UuidParameterTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<LeaseOccupant> */
final class LeaseOccupantRepository extends ServiceEntityRepository
{
    use UuidParameterTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeaseOccupant::class);
    }

    public function findForLease(Uuid $uuid, Lease $lease): ?LeaseOccupant
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.uuid = :uuid')
            ->andWhere('o.lease = :lease')
            ->setParameter('uuid', $this->bindableUuid($uuid))
            ->setParameter('lease', $lease)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Empêche deux périodes de présence simultanées pour la même personne et le même bail. */
    public function hasOverlappingPresence(Tenant $tenant, Lease $lease, \DateTimeImmutable $startDate, ?\DateTimeImmutable $endDate): bool
    {
        $qb = $this->createQueryBuilder('o')
            ->select('o.id')
            ->andWhere('o.tenant = :tenant')
            ->andWhere('o.lease = :lease')
            ->andWhere('(o.endDate IS NULL OR o.endDate >= :startDate)')
            ->setParameter('tenant', $tenant)
            ->setParameter('lease', $lease)
            ->setParameter('startDate', $startDate)
            ->setMaxResults(1);

        if ($endDate !== null) {
            $qb->andWhere('o.startDate <= :endDate')->setParameter('endDate', $endDate);
        }

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }
}
