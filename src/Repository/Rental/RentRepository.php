<?php

declare(strict_types=1);

namespace App\Repository\Rental;

use App\Entity\Identity\Organization;
use App\Entity\Rental\Lease;
use App\Entity\Rental\Rent;
use App\Repository\PaginatedResultTrait;
use App\Repository\UuidParameterTrait;
use App\Service\System\DateTimeService;
use App\Enum\LeaseStatus;
use App\Enum\RentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * RentRepository
 *
 * Package : Rental Management
 * Entité  : App\Entity\Rental\Rent
 *
 * @extends ServiceEntityRepository<Rent>
 */
class RentRepository extends ServiceEntityRepository
{
    use PaginatedResultTrait;
    use UuidParameterTrait;

    public function __construct(ManagerRegistry $registry, private readonly DateTimeService $dateTime)
    {
        parent::__construct($registry, Rent::class);
    }

    public function save(Rent $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Rent $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByUuid(Uuid $uuid): ?Rent
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.uuid = :uuid')
            ->setParameter('uuid', $this->bindableUuid($uuid))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche l'échéance d'un bail pour une période donnée
     * (le couple lease/period est unique).
     */
    public function findOneByLeaseAndPeriod(Lease $lease, \DateTimeImmutable $period): ?Rent
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.lease = :lease')
            ->andWhere('r.period = :period')
            ->setParameter('lease', $lease)
            ->setParameter('period', $period)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste toutes les échéances d'un bail, triées par période.
     *
     * @return Rent[]
     */
    public function findByLease(Lease $lease): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.lease = :lease')
            ->setParameter('lease', $lease)
            ->orderBy('r.period', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste paginée des échéances EN RETARD selon les organisations et villes autorisées.
     *
     * Une échéance est en retard si sa dueDate < aujourd'hui ET son statut n'est pas PAID.
     * On utilise le statut calculé (computed) qui inclut OVERDUE.
     *
     * @param list<int>|null $organizationIds
     * @param list<int>|null $cityIds
     *
     * @return array{items: list<Rent>, total: int}
     */
    public function findOverduePaginatedByOrganizationsAndCities(
        ?array $organizationIds = null,
        ?array $cityIds = null,
        int $page = 1,
        int $limit = 20,
        ?string $sortBy = 'dueDate',
        ?string $sortOrder = 'ASC'
    ): array {
        // Whitelist des champs de tri
        $allowedSortFields = ['dueDate', 'period', 'amount', 'createdAt'];
        $sortBy = in_array($sortBy, $allowedSortFields, true) ? $sortBy : 'dueDate';
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        $today = $this->dateTime->today();

        $qb = $this->createQueryBuilder('r')
            ->innerJoin('r.lease', 'l')
            ->andWhere('r.dueDate < :today')
            ->andWhere('r.status IN (:openStatuses)')
            ->setParameter('today', $today)
            ->setParameter('openStatuses', [\App\Enum\RentStatus::PENDING, \App\Enum\RentStatus::PARTIALLY_PAID, \App\Enum\RentStatus::OVERDUE])
            ->orderBy("r.$sortBy", $sortOrder);

        if ($organizationIds !== null && !empty($organizationIds)) {
            $qb->andWhere('l.organization IN (:orgs)')
                ->setParameter('orgs', $organizationIds);
        }

        if ($cityIds !== null && !empty($cityIds)) {
            $qb->innerJoin('l.unit', 'u')
                ->innerJoin('u.building', 'b')
                ->innerJoin('b.parcel', 'par')
                ->innerJoin('par.city', 'c')
                ->andWhere('c.id IN (:cities)')
                ->setParameter('cities', $cityIds);
        }

        return $this->fetchPaginated($qb, $page, $limit);
    }

    /**
     * Liste paginée générale des échéances selon les organisations, villes
     * et filtres (bail, statut calculé) autorisés.
     *
     * Le `status` comparé est le statut CALCULÉ (computed) exposé par
     * `RentResponse` : `overdue` n'est jamais persisté, une échéance
     * PENDING/PARTIALLY_PAID passée est OVERDUE à la lecture et ne doit pas
     * réapparaître sous son statut persistant.
     *
     * @param list<int>|null $organizationIds
     * @param list<int>|null $cityIds
     * @param Lease|null     $lease bail de rattachement (déjà autorisé par le service)
     *
     * @return array{items: list<Rent>, total: int}
     */
    public function findPaginatedAccessible(
        ?array $organizationIds = null,
        ?array $cityIds = null,
        ?Lease $lease = null,
        ?string $status = null,
        int $page = 1,
        int $limit = 20,
        ?string $sortBy = 'dueDate',
        ?string $sortOrder = 'ASC'
    ): array {
        $allowedSortFields = ['dueDate', 'period', 'amount', 'createdAt'];
        $sortBy = in_array($sortBy, $allowedSortFields, true) ? $sortBy : 'dueDate';
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        $today = $this->dateTime->today();

        $qb = $this->createQueryBuilder('r')
            ->innerJoin('r.lease', 'l')
            ->andWhere('l.deletedAt IS NULL')
            ->orderBy("r.$sortBy", $sortOrder);

        if ($organizationIds !== null && !empty($organizationIds)) {
            $qb->andWhere('l.organization IN (:orgs)')
                ->setParameter('orgs', $organizationIds);
        }

        if ($cityIds !== null && !empty($cityIds)) {
            $qb->innerJoin('l.unit', 'u')
                ->innerJoin('u.building', 'b')
                ->innerJoin('b.parcel', 'par')
                ->innerJoin('par.city', 'c')
                ->andWhere('c.id IN (:cities)')
                ->setParameter('cities', $cityIds);
        }

        if ($lease !== null) {
            $qb->andWhere('r.lease = :lease')
                ->setParameter('lease', $lease);
        }

        if ($status !== null && $status !== '') {
            if ($status === 'overdue') {
                $qb->andWhere('r.dueDate < :today')
                    ->andWhere('r.status IN (:openStatuses)')
                    ->setParameter('today', $today)
                    ->setParameter('openStatuses', [RentStatus::PENDING, RentStatus::PARTIALLY_PAID]);
            } else {
                $qb->andWhere('r.status = :status')
                    ->setParameter('status', RentStatus::from($status));

                if ($status !== 'paid') {
                    // pending / partially_paid : exclure les échéances passées
                    // qui sont OVERDUE à la lecture.
                    $qb->andWhere('r.dueDate >= :today')
                        ->setParameter('today', $today);
                }
            }
        }

        return $this->fetchPaginated($qb, $page, $limit);
    }

    /**
     * Liste les échéances en retard de paiement d'une organisation
     * (date d'échéance dépassée et statut non soldé).
     *
     * @return Rent[]
     */
    public function findOverdueByOrganization(Organization $organization): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.lease', 'l')
            ->andWhere('l.organization = :organization')
            ->andWhere('r.dueDate < :today')
            ->andWhere('r.status IN (:openStatuses)')
            ->setParameter('organization', $organization)
            ->setParameter('today', $this->dateTime->today())
            ->setParameter('openStatuses', [RentStatus::PENDING, RentStatus::PARTIALLY_PAID, RentStatus::OVERDUE])
            ->orderBy('r.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste paginée des échéances d'une organisation.
     *
     * @return array{items: list<Rent>, total: int}
     */
    public function findPaginatedByOrganization(
        Organization $organization,
        int $page,
        int $limit,
        ?string $search = null
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->innerJoin('r.lease', 'l')
            ->andWhere('l.organization = :organization')
            ->setParameter('organization', $organization)
            ->orderBy('r.dueDate', 'DESC');

        if ($search !== null && $search !== '') {
            $qb->andWhere('l.reference LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $this->fetchPaginated($qb, $page, $limit);
    }

    /**
     * Verrouille une échéance en mode pessimiste (SELECT ... FOR UPDATE).
     *
     * Retourne l'entité rechargée depuis la base avec le verrou, ou null
     * si l'entité n'existe plus.
     */
    public function lockForUpdate(Rent $rent): ?Rent
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.id = :id')
            ->setParameter('id', $rent->getId())
            ->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Somme des montants des loyers ATTENDUS (somme des Rent.amount)
     * pour les baux actifs, groupés par période (mois) et devise.
     *
     * Utilisé pour le résumé financier : comparer attendu vs encaissé.
     *
     * @param list<int>|null $organizationIds
     * @param list<int>|null $cityIds
     *
     * @return list<array{period: string, total: string, currency: string}>
     */
    public function getExpectedRentsSummary(
        ?array $organizationIds = null,
        ?array $cityIds = null,
        ?\DateTimeImmutable $periodFrom = null,
        ?\DateTimeImmutable $periodTo = null
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->select('DATE_FORMAT(r.period, \'%Y-%m\') as period, SUM(r.amount) as total, r.currency')
            ->innerJoin('r.lease', 'l')
            ->andWhere('l.status = :activeStatus')
            ->andWhere('l.deletedAt IS NULL')
            ->setParameter('activeStatus', \App\Enum\LeaseStatus::ACTIVE)
            ->groupBy('period, r.currency')
            ->orderBy('period', 'ASC');

        if ($organizationIds !== null && !empty($organizationIds)) {
            $qb->andWhere('l.organization IN (:orgs)')
                ->setParameter('orgs', $organizationIds);
        }

        if ($cityIds !== null && !empty($cityIds)) {
            $qb->innerJoin('l.unit', 'u')
                ->innerJoin('u.building', 'b')
                ->innerJoin('b.parcel', 'par')
                ->innerJoin('par.city', 'c')
                ->andWhere('c.id IN (:cities)')
                ->setParameter('cities', $cityIds);
        }

        if ($periodFrom !== null) {
            $qb->andWhere('r.period >= :periodFrom')
                ->setParameter('periodFrom', $periodFrom);
        }

        if ($periodTo !== null) {
            $qb->andWhere('r.period <= :periodTo')
                ->setParameter('periodTo', $periodTo);
        }

        return $qb->getQuery()->getResult();
    }
}