<?php

declare(strict_types=1);

namespace App\Repository\Property;

use App\Entity\Property\Building;
use App\Entity\Property\City;
use App\Entity\Property\Unit;
use App\Enum\LeaseStatus;
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

    /**
     * Liste les unités d'un bâtiment n'ayant aucun bail ACTIVE en cours
     * (utile pour l'affichage des disponibilités locatives).
     *
     * @return Unit[]
     */
    public function findAvailableByBuilding(Building $building): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.building = :building')
            ->andWhere('u.deletedAt IS NULL')
            ->andWhere(
                'NOT EXISTS (
                    SELECT 1 FROM App\Entity\Rental\Lease l
                    WHERE l.unit = u AND l.status = :activeStatus
                )'
            )
            ->setParameter('building', $building)
            ->setParameter('activeStatus', LeaseStatus::ACTIVE)
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

    /**
     * Liste paginée des unités accessibles.
     *
     * @param list<City> $cities villes autorisées pour le lecteur
     * @return array{items: list<Unit>, total: int}
     */
    public function findPaginatedAccessible(array $cities, int $page, int $limit, ?string $search = null): array
    {
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

        if ($search !== null && $search !== '') {
            $qb->andWhere('u.reference LIKE :search OR u.label LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $this->fetchPaginated($qb, $page, $limit);
    }
}
