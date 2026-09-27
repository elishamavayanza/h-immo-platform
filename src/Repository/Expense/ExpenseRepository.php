<?php

declare(strict_types=1);

namespace App\Repository\Expense;

use App\Entity\Property\City;
use App\Entity\Property\Parcel;
use App\Entity\Property\Building;
use App\Entity\Property\Unit;
use App\Entity\Staff\Worker;
use App\Entity\Expense\Expense;
use App\Enum\ExpenseCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * ExpenseRepository
 *
 * Package : Expense Management
 */
final class ExpenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Expense::class);
    }

    public function save(Expense $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Expense $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByUuid(Uuid $uuid): ?Expense
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.uuid = :uuid')
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les dépenses selon les filtres donnés.
     */
    public function findByFilters(
        ?array $organizationIds = null,
        ?array $cityIds = null,
        ?Parcel $parcel = null,
        ?Building $building = null,
        ?Unit $unit = null,
        ?Worker $worker = null,
        ?ExpenseCategory $category = null,
        ?\DateTimeImmutable $periodFrom = null,
        ?\DateTimeImmutable $periodTo = null,
        int $page = 1,
        int $limit = 20,
        string $sortBy = 'expenseDate',
        string $sortOrder = 'DESC'
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->select('e')
            ->orderBy("e.$sortBy", strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if ($organizationIds !== null && !empty($organizationIds)) {
            $qb->andWhere('e.organization IN (:orgs)')
                ->setParameter('orgs', $organizationIds);
        }

        if ($cityIds !== null && !empty($cityIds)) {
            $qb->andWhere('e.city IN (:cities)')
                ->setParameter('cities', $cityIds);
        }

        if ($parcel !== null) {
            $qb->andWhere('e.parcel = :parcel')
                ->setParameter('parcel', $parcel);
        }

        if ($building !== null) {
            $qb->andWhere('e.building = :building')
                ->setParameter('building', $building);
        }

        if ($unit !== null) {
            $qb->andWhere('e.unit = :unit')
                ->setParameter('unit', $unit);
        }

        if ($worker !== null) {
            $qb->andWhere('e.worker = :worker')
                ->setParameter('worker', $worker);
        }

        if ($category !== null) {
            $qb->andWhere('e.category = :category')
                ->setParameter('category', $category);
        }

        if ($periodFrom !== null) {
            $qb->andWhere('e.expenseDate >= :periodFrom')
                ->setParameter('periodFrom', $periodFrom);
        }

        if ($periodTo !== null) {
            $qb->andWhere('e.expenseDate <= :periodTo')
                ->setParameter('periodTo', $periodTo);
        }

        $items = $qb->getQuery()->getResult();

        // Total count
        $countQb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)');

        if ($organizationIds !== null && !empty($organizationIds)) {
            $countQb->andWhere('e.organization IN (:orgs)')
                ->setParameter('orgs', $organizationIds);
        }

        if ($cityIds !== null && !empty($cityIds)) {
            $countQb->andWhere('e.city IN (:cities)')
                ->setParameter('cities', $cityIds);
        }

        if ($parcel !== null) {
            $countQb->andWhere('e.parcel = :parcel')
                ->setParameter('parcel', $parcel);
        }

        if ($building !== null) {
            $countQb->andWhere('e.building = :building')
                ->setParameter('building', $building);
        }

        if ($unit !== null) {
            $countQb->andWhere('e.unit = :unit')
                ->setParameter('unit', $unit);
        }

        if ($worker !== null) {
            $countQb->andWhere('e.worker = :worker')
                ->setParameter('worker', $worker);
        }

        if ($category !== null) {
            $countQb->andWhere('e.category = :category')
                ->setParameter('category', $category);
        }

        if ($periodFrom !== null) {
            $countQb->andWhere('e.expenseDate >= :periodFrom')
                ->setParameter('periodFrom', $periodFrom);
        }

        if ($periodTo !== null) {
            $countQb->andWhere('e.expenseDate <= :periodTo')
                ->setParameter('periodTo', $periodTo);
        }

        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * Somme des montants par catégorie dans le périmètre donné.
     */
    public function sumByCategory(
        ?array $organizationIds = null,
        ?array $cityIds = null,
        ?\DateTimeImmutable $periodFrom = null,
        ?\DateTimeImmutable $periodTo = null
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->select('e.category, SUM(e.amount) as total, e.currency')
            ->groupBy('e.category, e.currency');

        if ($organizationIds !== null && !empty($organizationIds)) {
            $qb->andWhere('e.organization IN (:orgs)')
                ->setParameter('orgs', $organizationIds);
        }

        if ($cityIds !== null && !empty($cityIds)) {
            $qb->andWhere('e.city IN (:cities)')
                ->setParameter('cities', $cityIds);
        }

        if ($periodFrom !== null) {
            $qb->andWhere('e.expenseDate >= :periodFrom')
                ->setParameter('periodFrom', $periodFrom);
        }

        if ($periodTo !== null) {
            $qb->andWhere('e.expenseDate <= :periodTo')
                ->setParameter('periodTo', $periodTo);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Somme des montants par ville dans le périmètre donné.
     */
    public function sumByCity(
        ?array $organizationIds = null,
        ?\DateTimeImmutable $periodFrom = null,
        ?\DateTimeImmutable $periodTo = null
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->select('e.city, SUM(e.amount) as total, e.currency')
            ->innerJoin('e.city', 'c')
            ->addSelect('c.name as cityName')
            ->groupBy('e.city, e.currency, c.name');

        if ($organizationIds !== null && !empty($organizationIds)) {
            $qb->andWhere('e.organization IN (:orgs)')
                ->setParameter('orgs', $organizationIds);
        }

        if ($periodFrom !== null) {
            $qb->andWhere('e.expenseDate >= :periodFrom')
                ->setParameter('periodFrom', $periodFrom);
        }

        if ($periodTo !== null) {
            $qb->andWhere('e.expenseDate <= :periodTo')
                ->setParameter('periodTo', $periodTo);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Résumé financier par période (mois/année).
     */
    public function getFinancialSummary(
        ?array $organizationIds = null,
        ?array $cityIds = null,
        ?\DateTimeImmutable $periodFrom = null,
        ?\DateTimeImmutable $periodTo = null
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->select('DATE_FORMAT(e.expenseDate, \'%Y-%m\') as period, SUM(e.amount) as total, e.currency')
            ->groupBy('period, e.currency')
            ->orderBy('period', 'ASC');

        if ($organizationIds !== null && !empty($organizationIds)) {
            $qb->andWhere('e.organization IN (:orgs)')
                ->setParameter('orgs', $organizationIds);
        }

        if ($cityIds !== null && !empty($cityIds)) {
            $qb->andWhere('e.city IN (:cities)')
                ->setParameter('cities', $cityIds);
        }

        if ($periodFrom !== null) {
            $qb->andWhere('e.expenseDate >= :periodFrom')
                ->setParameter('periodFrom', $periodFrom);
        }

        if ($periodTo !== null) {
            $qb->andWhere('e.expenseDate <= :periodTo')
                ->setParameter('periodTo', $periodTo);
        }

        return $qb->getQuery()->getResult();
    }
}