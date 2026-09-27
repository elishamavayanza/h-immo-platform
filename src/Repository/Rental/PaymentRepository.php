<?php

declare(strict_types=1);

namespace App\Repository\Rental;

use App\Entity\Identity\Organization;
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
     * Liste tous les paiements enregistrés pour une échéance (Rent)
     * donnée, du plus récent au plus ancien.
     *
     * @return Payment[]
     */
    public function findByRent(Rent $rent): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.rent = :rent')
            ->setParameter('rent', $rent)
            ->orderBy('p.paymentDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le montant total déjà payé pour une échéance donnée
     * (somme simple ; la comparaison avec le montant dû relève
     * du service métier, pas du repository).
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
     * Liste paginée des paiements d'une organisation.
     *
     * Le périmètre est porté par `payment -> rent -> lease -> organization` :
     * aucun paiement ne peut être listé sans traverser un bail d'une
     * organisation autorisée.
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
