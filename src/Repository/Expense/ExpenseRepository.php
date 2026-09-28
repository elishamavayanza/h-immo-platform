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

use App\Repository\UuidParameterTrait;

/**
 * ExpenseRepository
 *
 * Package : Expense Management
 */
final class ExpenseRepository extends ServiceEntityRepository
{
    use UuidParameterTrait;
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
            ->setParameter('uuid', $this->bindableUuid($uuid))
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
        // Whitelist des champs de tri autorisés (injection DQL empêchée)
        $allowedSortFields = ['expenseDate', 'amount', 'createdAt', 'category'];
        $sortBy = in_array($sortBy, $allowedSortFields, true) ? $sortBy : 'expenseDate';
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createQueryBuilder('e')
            ->select('e')
            ->orderBy("e.$sortBy", $sortOrder)
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
     *
     * Le périmètre est OBLIGATOIRE : `$cityIds` filtre sur les villes, et
     * une ville appartient à une seule Organization, donc il borne déjà la
     * requête à un tenant. Une liste vide ne doit pas se lire comme « pas
     * de filtre » : elle signifie « aucune dépense visible » et renvoie
     * donc un résultat vide. Sans cette garde, un appelant qui aurait
     * perdu ses villes remontrerait toutes les dépenses de la plateforme.
     *
     * `e.city` est une association `to-one` : Doctrine l'hydrate en objet
     * `City`, et non en identifiant. `$row['city']->getUuid()` est donc
     * valide, contrairement à ce que suggère la lecture du `SELECT`.
     *
     * @param list<int> $cityIds
     *
     * @return list<array{city: City, cityName: string, total: string, currency: string}>
     */
    public function sumByCity(
        array $cityIds,
        ?\DateTimeImmutable $periodFrom = null,
        ?\DateTimeImmutable $periodTo = null
    ): array {
        if ($cityIds === []) {
            return [];
        }

        // Ni `e.city` ni `e.city.uuid` ne sont sélectionnables ici : DQL
        // refuse une association dans une requête groupée
        // (« Invalid PathExpression »), et un chemin qui la traverse est
        // résolu contre la classe racine, qui n'a pas de champ `uuid`. Le
        // champ doit être lu par l'alias du join, `c.uuid`.
        $qb = $this->createQueryBuilder('e')
            ->select('c.uuid as cityUuid, c.name as cityName, SUM(e.amount) as total, e.currency')
            ->innerJoin('e.city', 'c')
            ->groupBy('c.uuid, c.name, e.currency')
            ->andWhere('e.city IN (:cities)')
            ->setParameter('cities', $cityIds);

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