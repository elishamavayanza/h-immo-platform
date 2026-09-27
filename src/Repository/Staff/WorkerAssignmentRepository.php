<?php

declare(strict_types=1);

namespace App\Repository\Staff;

use App\Entity\Property\City;
use App\Entity\Property\Parcel;
use App\Entity\Property\Building;
use App\Entity\Property\Unit;
use App\Entity\Staff\WorkerAssignment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * WorkerAssignmentRepository
 *
 * Package : Staff Management
 */
final class WorkerAssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkerAssignment::class);
    }

    public function save(WorkerAssignment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(WorkerAssignment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByUuid(Uuid $uuid): ?WorkerAssignment
    {
        return $this->createQueryBuilder('wa')
            ->andWhere('wa.uuid = :uuid')
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByCity(City $city, ?\DateTimeImmutable $asOfDate = null): array
    {
        $date = $asOfDate ?? new \DateTimeImmutable();

        return $this->createQueryBuilder('wa')
            ->andWhere('wa.city = :city')
            ->andWhere('wa.startDate <= :date')
            ->andWhere('wa.endDate IS NULL OR wa.endDate >= :date')
            ->setParameter('city', $city)
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }

    public function findByWorker(Uuid $workerId): array
    {
        return $this->createQueryBuilder('wa')
            ->andWhere('wa.worker = :worker')
            ->setParameter('worker', $workerId)
            ->orderBy('wa.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByParcel(Parcel $parcel, ?\DateTimeImmutable $asOfDate = null): array
    {
        $date = $asOfDate ?? new \DateTimeImmutable();

        return $this->createQueryBuilder('wa')
            ->andWhere('wa.parcel = :parcel')
            ->andWhere('wa.startDate <= :date')
            ->andWhere('wa.endDate IS NULL OR wa.endDate >= :date')
            ->setParameter('parcel', $parcel)
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }

    public function findByBuilding(Building $building, ?\DateTimeImmutable $asOfDate = null): array
    {
        $date = $asOfDate ?? new \DateTimeImmutable();

        return $this->createQueryBuilder('wa')
            ->andWhere('wa.building = :building')
            ->andWhere('wa.startDate <= :date')
            ->andWhere('wa.endDate IS NULL OR wa.endDate >= :date')
            ->setParameter('building', $building)
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }

    public function findByUnit(Unit $unit, ?\DateTimeImmutable $asOfDate = null): array
    {
        $date = $asOfDate ?? new \DateTimeImmutable();

        return $this->createQueryBuilder('wa')
            ->andWhere('wa.unit = :unit')
            ->andWhere('wa.startDate <= :date')
            ->andWhere('wa.endDate IS NULL OR wa.endDate >= :date')
            ->setParameter('unit', $unit)
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les affectations actives dans les villes données.
     */
    public function findActiveByCities(array $cities, ?\DateTimeImmutable $asOfDate = null): array
    {
        if (empty($cities)) {
            return [];
        }

        $date = $asOfDate ?? new \DateTimeImmutable();

        return $this->createQueryBuilder('wa')
            ->andWhere('wa.city IN (:cities)')
            ->andWhere('wa.startDate <= :date')
            ->andWhere('wa.endDate IS NULL OR wa.endDate >= :date')
            ->setParameter('cities', $cities)
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }
}