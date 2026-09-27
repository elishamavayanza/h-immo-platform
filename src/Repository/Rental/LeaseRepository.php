<?php

declare(strict_types=1);

namespace App\Repository\Rental;

use App\Entity\Identity\Organization;
use App\Entity\Property\Unit;
use App\Entity\Rental\Lease;
use App\Repository\PaginatedResultTrait;
use App\Repository\UuidParameterTrait;
use App\Enum\LeaseStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * LeaseRepository
 *
 * Package : Rental Management
 * Entité  : App\Entity\Rental\Lease
 *
 * Fournit notamment la requête utilisée par la couche service pour
 * faire respecter la règle métier « une Unit ne peut avoir qu'un seul
 * Lease ACTIVE à la fois » — la règle elle-même reste hors de
 * l'entité et hors du repository (elle appartient à un service).
 *
 * @extends ServiceEntityRepository<Lease>
 */
class LeaseRepository extends ServiceEntityRepository
{
    use PaginatedResultTrait;
    use UuidParameterTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lease::class);
    }

    public function save(Lease $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Lease $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByUuid(Uuid $uuid): ?Lease
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.uuid = :uuid')
            ->andWhere('l.deletedAt IS NULL')
            ->setParameter('uuid', $this->bindableUuid($uuid))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche un bail par sa référence, au sein d'une organisation.
     */
    public function findOneByOrganizationAndReference(Organization $organization, string $reference): ?Lease
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.organization = :organization')
            ->andWhere('l.reference = :reference')
            ->andWhere('l.deletedAt IS NULL')
            ->setParameter('organization', $organization)
            ->setParameter('reference', $reference)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retourne le bail actuellement ACTIVE d'une Unit, s'il existe.
     * Point d'entrée de la règle « un seul bail actif par Unit ».
     *
     * `$excludeUuid` permet, lors d'une mise à jour, d'ignorer le bail
     * en cours de modification : sans cela, un bail déjà actif se
     * détecterait lui-même comme conflit et pourrait être bloqué à tort
     * (par exemple sur un simple changement de loyer).
     */
    public function findActiveLeaseForUnit(Unit $unit, ?Uuid $excludeUuid = null): ?Lease
    {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.unit = :unit')
            ->andWhere('l.status = :status')
            ->andWhere('l.deletedAt IS NULL')
            ->setParameter('unit', $unit)
            ->setParameter('status', LeaseStatus::ACTIVE);

        if ($excludeUuid !== null) {
            $qb->andWhere('l.uuid != :excludeUuid')
                ->setParameter('excludeUuid', $this->bindableUuid($excludeUuid));
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Existe-t-il déjà un bail actif sur cette unité ?
     *
     * Utilisé pour lever un conflit explicite (HTTP 409) sans charger
     * l'entité concurrente.
     */
    public function hasActiveLeaseForUnit(Unit $unit, ?Uuid $excludeUuid = null): bool
    {
        return $this->findActiveLeaseForUnit($unit, $excludeUuid) !== null;
    }

    /**
     * Liste l'historique complet des baux d'une Unit (tous statuts).
     *
     * @return Lease[]
     */
    public function findHistoryByUnit(Unit $unit): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.unit = :unit')
            ->andWhere('l.deletedAt IS NULL')
            ->setParameter('unit', $unit)
            ->orderBy('l.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste paginée des baux d'une organisation.
     *
     * @return array{items: list<Lease>, total: int}
     */
    public function findPaginatedByOrganization(
        Organization $organization,
        int $page,
        int $limit,
        ?string $search = null
    ): array {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.organization = :organization')
            ->andWhere('l.deletedAt IS NULL')
            ->setParameter('organization', $organization)
            ->orderBy('l.startDate', 'DESC');

        if ($search !== null && $search !== '') {
            $qb->andWhere('l.reference LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $this->fetchPaginated($qb, $page, $limit);
    }

    /**
     * Trouve les baux actifs selon les organisations et villes spécifiées.
     *
     * @return Lease[]
     */
    public function findActiveByOrganizationsAndCities(
        ?array $organizationIds = null,
        ?array $cityIds = null
    ): array {
        $qb = $this->createQueryBuilder('l')
            ->innerJoin('l.unit', 'u')
            ->innerJoin('u.building', 'b')
            ->innerJoin('b.parcel', 'par')
            ->innerJoin('par.city', 'c')
            ->andWhere('l.status = :status')
            ->andWhere('l.deletedAt IS NULL')
            ->setParameter('status', LeaseStatus::ACTIVE);

        if ($organizationIds !== null && !empty($organizationIds)) {
            $qb->andWhere('l.organization IN (:orgs)')
                ->setParameter('orgs', $organizationIds);
        }

        if ($cityIds !== null && !empty($cityIds)) {
            $qb->andWhere('c.id IN (:cities)')
                ->setParameter('cities', $cityIds);
        }

        return $qb->getQuery()->getResult();
    }
}
