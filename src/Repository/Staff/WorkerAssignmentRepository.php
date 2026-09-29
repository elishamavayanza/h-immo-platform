<?php

declare(strict_types=1);

namespace App\Repository\Staff;

use App\Entity\Property\City;
use App\Entity\Property\Parcel;
use App\Entity\Property\Building;
use App\Entity\Property\Unit;
use App\Entity\Staff\WorkerAssignment;
use App\Service\System\DateTimeService;
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
    public function __construct(ManagerRegistry $registry, private readonly DateTimeService $dateTime)
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
        $date = $asOfDate ?? $this->dateTime->now();

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
        $date = $asOfDate ?? $this->dateTime->now();

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
        $date = $asOfDate ?? $this->dateTime->now();

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
        $date = $asOfDate ?? $this->dateTime->now();

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

        $date = $asOfDate ?? $this->dateTime->now();

        return $this->createQueryBuilder('wa')
            ->andWhere('wa.city IN (:cities)')
            ->andWhere('wa.startDate <= :date')
            ->andWhere('wa.endDate IS NULL OR wa.endDate >= :date')
            ->setParameter('cities', $cities)
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les affectations chevauchant une période pour un worker dans une ville.
     */
    public function findOverlapping(
        \App\Entity\Staff\Worker $worker,
        \App\Entity\Property\City $city,
        \DateTimeImmutable $startDate,
        ?\DateTimeImmutable $endDate,
        ?\App\Entity\Staff\WorkerAssignment $exclude = null
    ): array {
        $qb = $this->createQueryBuilder('wa')
            ->andWhere('wa.worker = :worker')
            ->andWhere('wa.city = :city')
            ->andWhere('wa.startDate <= :endCheck')
            ->andWhere('wa.endDate IS NULL OR wa.endDate >= :startCheck')
            ->setParameter('worker', $worker)
            ->setParameter('city', $city)
            ->setParameter('startCheck', $startDate)
            ->setParameter('endCheck', $endDate ?? $this->dateTime->now()->modify('+100 years'));

        if ($exclude !== null) {
            $qb->andWhere('wa.uuid <> :excludeUuid')
                ->setParameter('excludeUuid', $exclude->getUuid());
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les affectations selon les filtres donnés.
     */
    public function findByFilters(
        ?array $cityIds = null,
        int $page = 1,
        int $limit = 20,
        string $sortBy = 'startDate',
        string $sortOrder = 'DESC'
    ): array {
        // Whitelist des champs de tri autorisés
        $allowedSortFields = ['startDate', 'endDate', 'monthlySalary', 'createdAt'];
        $sortBy = in_array($sortBy, $allowedSortFields, true) ? $sortBy : 'startDate';
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createQueryBuilder('wa')
            ->select('wa')
            ->orderBy("wa.$sortBy", $sortOrder)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if ($cityIds !== null && !empty($cityIds)) {
            $qb->andWhere('wa.city IN (:cities)')
                ->setParameter('cities', $cityIds);
        }

        $items = $qb->getQuery()->getResult();

        $countQb = $this->createQueryBuilder('wa')
            ->select('COUNT(wa.id)');

        if ($cityIds !== null && !empty($cityIds)) {
            $countQb->andWhere('wa.city IN (:cities)')
                ->setParameter('cities', $cityIds);
        }

        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }
}