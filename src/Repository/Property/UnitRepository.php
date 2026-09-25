<?php

declare(strict_types=1);

namespace App\Repository\Property;

use App\Entity\Property\Unit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * UnitRepository
 *
 * Package : Property Management
 * Entité  : App\Entity\Property\Unit
 *
 * @extends ServiceEntityRepository<Unit>
 */
class UnitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Unit::class);
    }

    public function save(Unit $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Unit $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByUuid(Uuid $uuid): ?Unit
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.uuid = :uuid')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche une unité par sa référence, au sein d'un bâtiment.
     */
    public function findOneByBuildingAndReference(int $buildingId, string $reference): ?Unit
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.building = :buildingId')
            ->andWhere('u.reference = :reference')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('buildingId', $buildingId)
            ->setParameter('reference', $reference)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste toutes les unités d'un bâtiment.
     *
     * @return Unit[]
     */
    public function findByBuilding(int $buildingId): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.building = :buildingId')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('buildingId', $buildingId)
            ->orderBy('u.reference', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste les unités d'un bâtiment n'ayant aucun bail ACTIVE en cours
     * (utile pour l'affichage des disponibilités locatives).
     *
     * @return Unit[]
     */
    public function findAvailableByBuilding(int $buildingId): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.building = :buildingId')
            ->andWhere('u.deletedAt IS NULL')
            ->andWhere(
                'NOT EXISTS (
                    SELECT 1 FROM App\Entity\Rental\Lease l
                    WHERE l.unit = u AND l.status = :activeStatus
                )'
            )
            ->setParameter('buildingId', $buildingId)
            ->setParameter('activeStatus', \App\Enum\LeaseStatus::ACTIVE)
            ->getQuery()
            ->getResult();
    }
}
