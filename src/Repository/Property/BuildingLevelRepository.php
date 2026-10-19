<?php

declare(strict_types=1);

namespace App\Repository\Property;

use App\Entity\Property\Building;
use App\Entity\Property\BuildingLevel;
use App\Repository\UuidParameterTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<BuildingLevel> */
final class BuildingLevelRepository extends ServiceEntityRepository
{
    use UuidParameterTrait;

    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, BuildingLevel::class); }

    public function findOneByUuid(Uuid $uuid): ?BuildingLevel
    {
        return $this->createQueryBuilder('level')->andWhere('level.uuid = :uuid')->andWhere('level.deletedAt IS NULL')
            ->setParameter('uuid', $this->bindableUuid($uuid))->getQuery()->getOneOrNullResult();
    }

    /** @return list<BuildingLevel> */
    public function findByBuilding(Building $building): array
    {
        return $this->createQueryBuilder('level')->andWhere('level.building = :building')->andWhere('level.deletedAt IS NULL')
            ->setParameter('building', $building)->orderBy('level.displayOrder', 'ASC')->addOrderBy('level.label', 'ASC')->getQuery()->getResult();
    }

    public function referenceExists(Building $building, string $reference, ?BuildingLevel $exclude = null): bool
    {
        $qb = $this->createQueryBuilder('level')->select('COUNT(level.id)')->andWhere('level.building = :building')
            ->andWhere('level.reference = :reference')->andWhere('level.deletedAt IS NULL')
            ->setParameter('building', $building)->setParameter('reference', $reference);
        if ($exclude !== null) $qb->andWhere('level.id != :exclude')->setParameter('exclude', $exclude->getId());
        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function hasFloorAbove(Building $building, int $maximumFloor): bool
    {
        return (int) $this->createQueryBuilder('level')
            ->select('COUNT(level.id)')
            ->andWhere('level.building = :building')
            ->andWhere('level.displayOrder > :maximumFloor')
            ->andWhere('level.deletedAt IS NULL')
            ->setParameter('building', $building)
            ->setParameter('maximumFloor', $maximumFloor)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function highestFloor(Building $building): int
    {
        return (int) $this->createQueryBuilder('level')
            ->select('COALESCE(MAX(level.displayOrder), 0)')
            ->andWhere('level.building = :building')
            ->andWhere('level.deletedAt IS NULL')
            ->setParameter('building', $building)
            ->getQuery()
            ->getSingleScalarResult();
    }

}
