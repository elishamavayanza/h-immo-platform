<?php

declare(strict_types=1);

namespace App\Repository\Rental;

use App\Entity\Identity\Organization;
use App\Entity\Rental\Lease;
use App\Entity\Rental\Payment;
use App\Entity\Rental\Rent;
use App\Repository\PaginatedResultTrait;
use App\Repository\UuidParameterTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * PaymentRepository
 *
 * Package : Rental Management
 * Entité  : App\Entity\Rental\Payment
 *
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    use PaginatedResultTrait;
    use UuidParameterTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function save(Payment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Payment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByUuid(Uuid $uuid): ?Payment
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.uuid = :uuid')
            ->setParameter('uuid', $this->bindableUuid($uuid))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste paginée des paiements selon les organisations et villes autorisées.
     *
     * @param list<int>|null $organizationIds
     * @param list<int>|null $cityIds
     *
     * @return array{items: list<Payment>, total: int}
     */
    public function findPaginatedByOrganizationsAndCities(
        ?array $organizationIds = null,
        ?array $cityIds = null,
        int $page = 1,
        int $limit = 20,
        ?string $search = null,
        ?string $sortBy = 'paymentDate',
        ?string $sortOrder = 'DESC'
    ): array {
        // Whitelist des champs de tri
        $allowedSortFields = ['paymentDate', 'amount', 'reference', 'receiptNumber', 'createdAt'];
        $sortBy = in_array($sortBy, $allowedSortFields, true) ? $sortBy : 'paymentDate';
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createQueryBuilder('p')
            ->innerJoin('p.rent', 'r')
            ->innerJoin('r.lease', 'l')
            ->orderBy("p.$sortBy", $sortOrder);

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

        if ($search !== null && $search !== '') {
            $qb->andWhere('p.reference LIKE :search OR p.receiptNumber LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $this->fetchPaginated($qb, $page, $limit);
    }

    /**
     * Liste paginée des paiements d'une organisation.
     *
     * @return array{items: list<Payment>, total: int}
     */
    public function findPaginatedByOrganization(
        Organization $organization,
        int $page,
        int $limit,
        ?string $search = null
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->innerJoin('p.rent', 'r')
            ->innerJoin('r.lease', 'l')
            ->andWhere('l.organization = :organization')
            ->setParameter('organization', $organization)
            ->orderBy('p.paymentDate', 'DESC');

        if ($search !== null && $search !== '') {
            $qb->andWhere('p.reference LIKE :search OR p.receiptNumber LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $this->fetchPaginated($qb, $page, $limit);
    }

    /**
     * Somme des montants par bail.
     */
    public function sumAmountByRent(Rent $rent): string
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount) AS total')
            ->andWhere('p.rent = :rent')
            ->setParameter('rent', $rent)
            ->getQuery()
            ->getSingleScalarResult();

        return (string) ($result ?? '0');
    }

    /**
     * Résumé financier par période pour les paiements.
     *
     * @return array<array{period: string, total: string, currency: string}>
     */
    public function getFinancialSummary(
        ?array $organizationIds = null,
        ?array $cityIds = null,
        ?\DateTimeImmutable $periodFrom = null,
        ?\DateTimeImmutable $periodTo = null
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->select('DATE_FORMAT(p.paymentDate, \'%Y-%m\') as period, SUM(p.amount) as total, p.currency')
            ->innerJoin('p.rent', 'r')
            ->innerJoin('r.lease', 'l')
            ->groupBy('period, p.currency')
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
            $qb->andWhere('p.paymentDate >= :periodFrom')
                ->setParameter('periodFrom', $periodFrom);
        }

        if ($periodTo !== null) {
            $qb->andWhere('p.paymentDate <= :periodTo')
                ->setParameter('periodTo', $periodTo);
        }

        return $qb->getQuery()->getResult();
    }
}