<?php

declare(strict_types=1);

namespace App\Repository\Property;

use App\Entity\Property\AccessPoint;
use App\Entity\Property\Building;
use App\Entity\Property\Parcel;
use App\Repository\UuidParameterTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<AccessPoint> */
final class AccessPointRepository extends ServiceEntityRepository
{
    use UuidParameterTrait;

    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, AccessPoint::class); }

    public function findOneByUuid(Uuid $uuid): ?AccessPoint
    {
        return $this->createQueryBuilder('a')->andWhere('a.uuid = :uuid')->andWhere('a.deletedAt IS NULL')
            ->setParameter('uuid', $this->bindableUuid($uuid))->getQuery()->getOneOrNullResult();
    }

    /** @return list<AccessPoint> */
    public function findByBuilding(Building $building): array
    {
        return $this->createQueryBuilder('a')->addSelect('units')->leftJoin('a.units', 'units', 'WITH', 'units.deletedAt IS NULL')
            ->andWhere('a.building = :building')->andWhere('a.deletedAt IS NULL')->setParameter('building', $building)
            ->orderBy('a.reference', 'ASC')->getQuery()->getResult();
    }

    /** @return list<AccessPoint> */
    public function findByParcel(Parcel $parcel, ?Building $building = null): array
    {
        $qb = $this->createQueryBuilder('a')->addSelect('units')
            ->leftJoin('a.units', 'units', 'WITH', 'units.deletedAt IS NULL')
            ->andWhere('a.parcel = :parcel')->andWhere('a.deletedAt IS NULL')
            ->setParameter('parcel', $parcel)->orderBy('a.reference', 'ASC');
        if ($building !== null) {
            $qb->andWhere('a.building IS NULL OR a.building = :building')->setParameter('building', $building);
        } else {
            $qb->andWhere('a.building IS NULL');
        }
        return $qb->getQuery()->getResult();
    }

    public function referenceExists(Parcel $parcel, string $reference, ?AccessPoint $exclude = null): bool
    {
        $qb = $this->createQueryBuilder('a')->select('COUNT(a.id)')->andWhere('a.parcel = :parcel')
            ->andWhere('a.reference = :reference')->andWhere('a.deletedAt IS NULL')
            ->setParameter('parcel', $parcel)->setParameter('reference', $reference);
        if ($exclude !== null) $qb->andWhere('a.id != :exclude')->setParameter('exclude', $exclude->getId());
        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
