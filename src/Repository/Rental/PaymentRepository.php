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
     * Recharge un paiement en verrou pessimiste (SELECT ... FOR UPDATE).
     *
     * Sert à sérialiser deux annulations concurrentes du même paiement : la
     * contrainte d'unicité sur `reversal_of_id` reste la garantie ultime, mais
     * le verrou évite de créer la contre-écriture avant de constater que
     * l'originale a déjà été annulée.
     */
    public function lockForUpdate(Payment $payment): ?Payment
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.id = :id')
            ->setParameter('id', $payment->getId())
            ->getQuery()
            ->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE)
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
        ?string $sortOrder = 'DESC',
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null
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

        if ($from !== null) {
            $qb->andWhere('p.paymentDate >= :from')
                ->setParameter('from', $from);
        }

        if ($to !== null) {
            $qb->andWhere('p.paymentDate <= :to')
                ->setParameter('to', $to);
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
            // `amount` est exprimé dans la devise effectivement encaissée.
            // Pour comparer au loyer, les paiements cross-devises doivent
            // utiliser leur montant d'origine, déjà exprimé dans la devise
            // de l'échéance. Les contre-écritures conservent ce montant avec
            // son signe négatif.
            ->select('SUM(CASE WHEN p.originalAmount IS NOT NULL THEN p.originalAmount ELSE p.amount END) AS total')
            ->andWhere('p.rent = :rent')
            ->setParameter('rent', $rent)
            ->getQuery()
            ->getSingleScalarResult();

        return (string) ($result ?? '0');
    }

    /**
     * Somme normalisée à la devise de l'échéance, à une date métier donnée.
     * Les écritures enregistrées après cette date ne modifient pas la vue
     * historique du solde.
     */
    public function sumAmountByRentAsOf(Rent $rent, \DateTimeImmutable $asOfDate): string
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(CASE WHEN p.originalAmount IS NOT NULL THEN p.originalAmount ELSE p.amount END) AS total')
            ->andWhere('p.rent = :rent')
            ->andWhere('p.paymentDate <= :asOfDate')
            ->setParameter('rent', $rent)
            ->setParameter('asOfDate', $asOfDate)
            ->getQuery()
            ->getSingleScalarResult();

        return (string) ($result ?? '0');
    }

    public function hasPaymentsForRent(Rent $rent): bool
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.rent = :rent')
            ->setParameter('rent', $rent)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * Résumé des loyers encaissés par période, normalisés dans la devise
     * contractuelle de l'échéance. Pour un paiement converti, `originalAmount`
     * est la part qui réduit le loyer et `originalCurrency` est sa devise.
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
            ->select('DATE_FORMAT(p.paymentDate, \'%Y-%m\') as period, DATE_FORMAT(p.paymentDate, \'%Y-%m-%d\') as effectiveDate, SUM(CASE WHEN p.originalAmount IS NOT NULL THEN p.originalAmount ELSE p.amount END) as total, CASE WHEN p.originalCurrency IS NOT NULL THEN p.originalCurrency ELSE p.currency END as currency, c.id as cityId')
            ->innerJoin('p.rent', 'r')
            ->innerJoin('r.lease', 'l')
            ->innerJoin('l.unit', 'u')
            ->innerJoin('u.building', 'b')
            ->innerJoin('b.parcel', 'par')
            ->innerJoin('par.city', 'c')
            ->groupBy('effectiveDate, currency, c.id')
            ->orderBy('period', 'ASC');

        if ($organizationIds !== null && !empty($organizationIds)) {
            $qb->andWhere('l.organization IN (:orgs)')
                ->setParameter('orgs', $organizationIds);
        }

        if ($cityIds !== null && !empty($cityIds)) {
            $qb->andWhere('c.id IN (:cities)')
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
