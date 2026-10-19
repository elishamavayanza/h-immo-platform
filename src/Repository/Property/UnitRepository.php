<?php

declare(strict_types=1);

namespace App\Repository\Property;

use App\Entity\Identity\Organization;
use App\Entity\Property\Building;
use App\Entity\Property\BuildingLevel;
use App\Entity\Property\City;
use App\Entity\Property\Unit;
use App\Enum\Currency;
use App\Enum\LeaseStatus;
use App\Enum\UnitType;
use App\Enum\UnitOperationalStatus;
use App\Repository\PaginatedResultTrait;
use App\Repository\UuidParameterTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * UnitRepository
 *
 * Package : Property Management
 * Entité  : App\Entity\Property\Unit
 *
 * Le périmètre est `unit -> building -> parcel -> city`. Le nombre
 * d'unités est borné par la liste de villes autorisées.
 *
 * @extends ServiceEntityRepository<Unit>
 */
class UnitRepository extends ServiceEntityRepository
{
    use PaginatedResultTrait;
    use UuidParameterTrait;

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
            ->setParameter('uuid', $this->bindableUuid($uuid))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche une unité par sa référence, au sein d'un bâtiment.
     */
    public function findOneByBuildingAndReference(Building $building, string $reference): ?Unit
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.building = :building')
            ->andWhere('u.reference = :reference')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('building', $building)
            ->setParameter('reference', $reference)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste toutes les unités d'un bâtiment.
     *
     * @return Unit[]
     */
    public function findByBuilding(Building $building): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.building = :building')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('building', $building)
            ->orderBy('u.reference', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function hasFloorAbove(Building $building, int $maximumFloor): bool
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.building = :building')
            ->andWhere('u.floor > :maximumFloor')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('building', $building)
            ->setParameter('maximumFloor', $maximumFloor)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function highestFloor(Building $building): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COALESCE(MAX(u.floor), 0)')
            ->andWhere('u.building = :building')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('building', $building)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<Unit> */
    public function findByLevel(BuildingLevel $level): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.level = :level')
            ->setParameter('level', $level)
            ->getQuery()
            ->getResult();
    }

    /** @param list<int> $cityIds */
    public function countByCityIds(array $cityIds): int
    {
        if ($cityIds === []) {
            return 0;
        }

        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->innerJoin('u.building', 'b')
            ->innerJoin('b.parcel', 'p')
            ->andWhere('p.city IN (:cities)')
            ->andWhere('u.deletedAt IS NULL')
            ->andWhere('b.deletedAt IS NULL')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('cities', $cityIds)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Agrège les unités et leur occupation courante par ville en une requête.
     * Les filtres reproduisent les suppressions logiques appliquées aux listes
     * du patrimoine et la règle d'occupation par bail ACTIVE.
     *
     * @param list<int> $cityIds
     * @return list<array{cityId: int, cityName: string, totalUnits: int, occupiedUnits: int}>
     */
    public function getCurrentOccupancyByCityIds(array $cityIds, \DateTimeImmutable $asOfDate): array
    {
        if ($cityIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('u')
            ->select('c.id AS cityId, c.name AS cityName, COUNT(DISTINCT u.id) AS totalUnits, COUNT(DISTINCT CASE WHEN l.id IS NOT NULL THEN u.id ELSE NULL END) AS occupiedUnits')
            ->innerJoin('u.building', 'b')
            ->innerJoin('b.parcel', 'p')
            ->innerJoin('p.city', 'c')
            ->leftJoin('App\\Entity\\Rental\\Lease', 'l', 'WITH', '(l.unit = u OR u MEMBER OF l.additionalUnits) AND l.status = :activeStatus AND l.deletedAt IS NULL AND l.startDate <= :asOfDate AND (l.endDate IS NULL OR l.endDate >= :asOfDate)')
            ->andWhere('c.id IN (:cities)')
            ->andWhere('u.deletedAt IS NULL')
            ->andWhere('b.deletedAt IS NULL')
            ->andWhere('p.deletedAt IS NULL')
            ->groupBy('c.id, c.name')
            ->setParameter('cities', $cityIds)
            ->setParameter('activeStatus', LeaseStatus::ACTIVE)
            ->setParameter('asOfDate', $asOfDate)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): array => [
            'cityId' => (int) $row['cityId'],
            'cityName' => (string) $row['cityName'],
            'totalUnits' => (int) $row['totalUnits'],
            'occupiedUnits' => (int) $row['occupiedUnits'],
        ], $rows);
    }

    /**
     * Liste les unités d'un bâtiment n'ayant aucun bail ACTIVE en cours
     * (utile pour l'affichage des disponibilités locatives).
     *
     * @return Unit[]
     */
    public function findAvailableByBuilding(Building $building, \DateTimeImmutable $asOfDate): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.building = :building')
            ->andWhere('u.deletedAt IS NULL')
            ->andWhere(
                'NOT EXISTS (
                    SELECT 1 FROM App\Entity\Rental\Lease l
                    WHERE (l.unit = u OR u MEMBER OF l.additionalUnits) AND l.status = :activeStatus AND l.deletedAt IS NULL
                        AND l.startDate <= :asOfDate AND (l.endDate IS NULL OR l.endDate >= :asOfDate)
                )'
            )
            ->setParameter('building', $building)
            ->setParameter('activeStatus', LeaseStatus::ACTIVE)
            ->setParameter('asOfDate', $asOfDate)
            ->getQuery()
            ->getResult();
    }

    /**
     * Verrouille la ligne unité en écriture (SELECT ... FOR UPDATE).
     *
     * MariaDB ne permettant pas d'index unique partiel, la règle
     * « un seul bail actif par unité » ne peut pas être garantie par une
     * contrainte `UNIQUE (unit_id, status)`, qui interdait aussi les
     * baux historiques terminés partageant le même statut. Ce verrou
     * pessimiste sérialise les transactions concurrentes qui ciblent la
     * même unité ; le contrôle métier s'exécute ensuite dans cette
     * transaction, ce qui élimine la fenêtre entre le SELECT et l'INSERT.
     *
     * À appeler dans une transaction déjà ouverte, avant de vérifier
     * l'absence de bail actif.
     */
    public function lockForUpdate(Unit $unit): void
    {
        $entityManager = $this->getEntityManager();
        $connection = $entityManager->getConnection();

        // `FOR UPDATE` n'a de sens qu'en.transaction : hors transaction,
        // MariaDB l'accepte silencieusement et le verrou est relâché
        // immédiatement, ce qui laisserait passer exactement la
        // concurrence que ce verrou sert à empêcher.
        if (!$connection->isTransactionActive()) {
            throw new \LogicException(sprintf(
                'UnitRepository::lockForUpdate() doit être appelée dans une transaction'
                . ' (voir EntityManager::wrapInTransaction()).'
            ));
        }

        $entityManager
            ->createQuery(
                'SELECT u.id FROM App\\Entity\\Property\\Unit u WHERE u.id = :id'
            )
            ->setParameter('id', $unit->getId())
            ->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE)
            ->getSingleScalarResult();
    }

    /** Racine commune utilisée pour sérialiser les baux des espaces imbriqués. */
    public function findSpaceRoot(Unit $unit): Unit
    {
        $root = $unit;
        while ($root->getParentUnit() !== null) {
            $root = $root->getParentUnit();
        }

        return $root;
    }

    /** @return list<Unit> Unité, parents et subdivisions, sans espaces supprimés. */
    public function findRelatedSpaces(Unit $unit): array
    {
        $root = $this->findSpaceRoot($unit);
        $result = [];
        $pending = [$root];
        $seen = [];

        while ($pending !== []) {
            $current = array_shift($pending);
            $key = (string) $current->getId();
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $current;

            $children = $this->createQueryBuilder('u')
                ->andWhere('u.parentUnit = :parent')
                ->andWhere('u.deletedAt IS NULL')
                ->setParameter('parent', $current)
                ->getQuery()
                ->getResult();
            array_push($pending, ...$children);
        }

        return $result;
    }

    /** @return list<Unit> L'unité, ses ancêtres et ses descendants, sans ses frères indépendants. */
    public function findOverlappingSpaces(Unit $unit, bool $includeArchivedDescendants = false): array
    {
        $spaces = [];
        $parent = $unit;
        while ($parent !== null) {
            $spaces[(string) $parent->getId()] = $parent;
            $parent = $parent->getParentUnit();
        }

        $pending = [$unit];
        while ($pending !== []) {
            $current = array_shift($pending);
            $query = $this->createQueryBuilder('u')
                ->andWhere('u.parentUnit = :parent')
                ->setParameter('parent', $current);
            if (!$includeArchivedDescendants) {
                $query->andWhere('u.deletedAt IS NULL');
            }
            $children = $query->getQuery()->getResult();
            foreach ($children as $child) {
                $key = (string) $child->getId();
                if (isset($spaces[$key])) {
                    continue;
                }
                $spaces[$key] = $child;
                $pending[] = $child;
            }
        }

        return array_values($spaces);
    }

    /** Empêche un cycle si l'espace courant devient enfant d'un de ses descendants. */
    public function isDescendantOf(Unit $candidate, Unit $ancestor): bool
    {
        $parent = $candidate->getParentUnit();
        while ($parent !== null) {
            if ($parent->getId() === $ancestor->getId()) {
                return true;
            }
            $parent = $parent->getParentUnit();
        }

        return false;
    }

    public function hasActiveSubspaces(Unit $unit): bool
    {
        $pending = [$unit];
        $visited = [(string) $unit->getId() => true];

        while ($pending !== []) {
            $parent = array_shift($pending);
            $children = $this->createQueryBuilder('u')
                ->andWhere('u.parentUnit = :parent')
                ->setParameter('parent', $parent)
                ->getQuery()
                ->getResult();

            foreach ($children as $child) {
                $id = (string) $child->getId();
                if (isset($visited[$id])) {
                    continue;
                }
                $visited[$id] = true;
                if ($child->getDeletedAt() === null) {
                    return true;
                }
                $pending[] = $child;
            }
        }

        return false;
    }

    /**
     * Liste paginée des unités accessibles.
     *
     * @param list<City>    $cities   villes autorisées pour le lecteur
     * @param Building|null $building bâtiment parent (optionnel, résolu et
     *                                autorisé par le service ; sinon `null`)
     * @return array{items: list<Unit>, total: int}
     */
    public function findPaginatedAccessible(
        array $cities,
        int $page,
        int $limit,
        ?string $search = null,
        ?Building $building = null
    ): array {
        if ($cities === []) {
            return ['items' => [], 'total' => 0];
        }

        $qb = $this->createQueryBuilder('u')
            ->innerJoin('u.building', 'b')
            ->innerJoin('b.parcel', 'p')
            ->andWhere('u.deletedAt IS NULL')
            ->andWhere('b.deletedAt IS NULL')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.city IN (:cities)')
            ->setParameter('cities', $cities)
            ->orderBy('u.reference', 'ASC');

        if ($building !== null) {
            $qb->andWhere('u.building = :building')
                ->setParameter('building', $building);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('u.reference LIKE :search OR u.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $this->fetchPaginated($qb, $page, $limit);
    }

    /**
     * Unités publiées ET libres, pour la vitrine publique.
     *
     * Les deux conditions sont indissociables, et c'est le cœur de la règle
     * métier de cette fonctionnalité :
     *
     *  - `is_published` est la *décision* de l'ADMIN_VILLE, pas l'état de
     *    disponibilité ;
     *  - l'absence de bail `ACTIVE` est l'*état réel*, recalculé à chaque
     *    lecture.
     *
     * Ne filtrer que sur `is_published` laisserait afficher une unité louée ;
     * ne filtrer que sur l'absence de bail publierait des unités qu'aucun
     * gestionnaire n'a choisi d'exposer. Les lignes supprimées sont exclues à
     * chaque niveau de la chaîne, `SoftDeletableEntity` n'ayant pas de filtre
     * Doctrine automatique.
     *
     * Le `NOT EXISTS` porte sur `lease.unit_id` et `lease.status`, tous deux
     * indexés : la sous-requête corrélée ne se réexécute pas ligne à ligne.
     *
     * @param list<City> $cities villes retenues par le filtre, ou [] pour toutes
     *                          celles de l'Organization
     * @return array{items: list<Unit>, total: int}
     */
    public function findPublishedAndVacant(
        Organization $organization,
        array $cities = [],
        int $page = 1,
        int $limit = 20,
        ?UnitType $type = null,
        ?int $minBedrooms = null,
        ?string $minRent = null,
        ?string $maxRent = null,
        ?Currency $currency = null
    ): array {
        $qb = $this->createQueryBuilder('u')
            ->innerJoin('u.building', 'b')
            ->innerJoin('b.parcel', 'p')
            ->innerJoin('p.city', 'c')
            ->andWhere('u.deletedAt IS NULL')
            ->andWhere('b.deletedAt IS NULL')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('c.deletedAt IS NULL')
            ->andWhere('c.organization = :organization')
            ->setParameter('organization', $organization)
            ->andWhere('u.isPublished = :published')
            ->setParameter('published', true)
            ->andWhere('u.operationalStatus = :operationalAvailable')
            ->setParameter('operationalAvailable', UnitOperationalStatus::AVAILABLE)
            ->andWhere(
                'NOT EXISTS (
                    SELECT 1 FROM App\Entity\Rental\Lease l
                    WHERE (l.unit = u OR u MEMBER OF l.additionalUnits) AND l.status = :activeStatus AND l.deletedAt IS NULL
                )'
            )
            ->setParameter('activeStatus', LeaseStatus::ACTIVE);

        if ($cities !== []) {
            $qb->andWhere('c IN (:cities)')->setParameter('cities', $cities);
        }

        if ($type !== null) {
            $qb->andWhere('u.type = :type')->setParameter('type', $type);
        }

        if ($minBedrooms !== null) {
            $qb->andWhere('u.bedrooms >= :minBedrooms')->setParameter('minBedrooms', $minBedrooms);
        }

        // `monthlyRent` est un DECIMAL : les bornes sont comparées comme des
        // chaînes normalisées, jamais converties en float (§ Argent).
        if ($minRent !== null && $minRent !== '') {
            $qb->andWhere('u.monthlyRent >= :minRent')->setParameter('minRent', $minRent);
        }

        if ($maxRent !== null && $maxRent !== '') {
            $qb->andWhere('u.monthlyRent <= :maxRent')->setParameter('maxRent', $maxRent);
        }

        // Un filtre de loyer sans devise reviendrait à comparer des montants de
        // devises différentes : le critère de disponibilité est donc la
        // cohérence du regroupement, pas une commodité d'affichage.
        if ($currency !== null) {
            $qb->andWhere('u.currency = :currency')->setParameter('currency', $currency);
        }

        $qb->orderBy('u.monthlyRent', 'ASC')->addOrderBy('u.id', 'ASC');

        return $this->fetchPaginated($qb, $page, $limit);
    }

    /**
     * Une unité publiée, et toujours libre, vue par son UUID public.
     *
     * Même couple de conditions que `findPublishedAndVacant()`, et pour la
     * même raison : le détail d'une annonce ne doit pas devenir accessible par
     * la seule connaissance d'un UUID, y compris après la signature d'un bail.
     */
    public function findPublishedAndVacantByUuid(Uuid $uuid, Organization $organization): ?Unit
    {
        return $this->createQueryBuilder('u')
            ->innerJoin('u.building', 'b')
            ->innerJoin('b.parcel', 'p')
            ->innerJoin('p.city', 'c')
            ->andWhere('u.uuid = :uuid')
            ->setParameter('uuid', $this->bindableUuid($uuid))
            ->andWhere('u.deletedAt IS NULL')
            ->andWhere('b.deletedAt IS NULL')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('c.deletedAt IS NULL')
            ->andWhere('c.organization = :organization')
            ->setParameter('organization', $organization)
            ->andWhere('u.isPublished = :published')
            ->setParameter('published', true)
            ->andWhere('u.operationalStatus = :operationalAvailable')
            ->setParameter('operationalAvailable', UnitOperationalStatus::AVAILABLE)
            ->andWhere(
                'NOT EXISTS (
                    SELECT 1 FROM App\Entity\Rental\Lease l
                    WHERE (l.unit = u OR u MEMBER OF l.additionalUnits) AND l.status = :activeStatus AND l.deletedAt IS NULL
                )'
            )
            ->setParameter('activeStatus', LeaseStatus::ACTIVE)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
