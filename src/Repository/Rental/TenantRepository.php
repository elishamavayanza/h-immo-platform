<?php

declare(strict_types=1);

namespace App\Repository\Rental;

use App\Entity\Identity\Organization;
use App\Entity\Property\City;
use App\Entity\Rental\Tenant;
use App\Repository\PaginatedResultTrait;
use App\Repository\UuidParameterTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * TenantRepository
 *
 * Package : Rental Management
 * Entité  : App\Entity\Rental\Tenant
 *
 * @extends ServiceEntityRepository<Tenant>
 */
class TenantRepository extends ServiceEntityRepository
{
    use PaginatedResultTrait;
    use UuidParameterTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tenant::class);
    }

    public function save(Tenant $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Tenant $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByUuid(Uuid $uuid): ?Tenant
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.uuid = :uuid')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('uuid', $this->bindableUuid($uuid))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche des locataires d'une organisation par téléphone ou email
     * (recherche simple, utile pour éviter les doublons à la saisie).
     *
     * @return Tenant[]
     */
    public function searchByOrganization(Organization $organization, string $term): array
    {
        // `t.fullName` et non `t.lastName` : l'entité ne stocke qu'un nom
        // composed, `firstName`/`lastName` n'existent qu'au niveau du DTO
        // de requête. Interroger `t.lastName` levait une erreur DQL à
        // l'exécution ("Field 't.lastName' does not exist").
        return $this->createQueryBuilder('t')
            ->andWhere('t.organization = :organization')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere(
                't.fullName LIKE :term OR t.companyName LIKE :term'
                . ' OR t.phone LIKE :term OR t.email LIKE :term'
            )
            ->setParameter('organization', $organization)
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('t.fullName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste paginée des locataires d'une organisation.
     *
     * @return array{items: list<Tenant>, total: int}
     */
    public function findPaginatedByOrganization(
        Organization $organization,
        int $page,
        int $limit,
        ?string $search = null
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.organization = :organization')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('organization', $organization)
            ->orderBy('t.fullName', 'ASC');

        if ($search !== null && $search !== '') {
            $qb->andWhere(
                't.fullName LIKE :search OR t.companyName LIKE :search'
                . ' OR t.phone LIKE :search OR t.email LIKE :search'
            )
                ->setParameter('search', '%' . $search . '%');
        }

        return $this->fetchPaginated($qb, $page, $limit);
    }

    /**
     * Liste paginée des locataires d'une ou plusieurs organizations, avec
     * restriction territoriale optionnelle.
     *
     * Un locataire n'étant rattaché à aucune ville, le périmètre
     * `ADMIN_VILLE` ne peut pas être filtré directement : il se déduit
     * des baux que le locataire détient. Un locataire n'est donc visible
     * par un administrateur de ville que s'il occupe, via un bail, une
     * unité située dans l'une de ses villes attitrées.
     *
     * @param list<Organization> $organizations
     * @param list<City>|null   $allowedCities `null` = pas de restriction
     * @return array{items: list<Tenant>, total: int}
     */
    public function findPaginatedAccessible(
        array $organizations,
        ?array $allowedCities,
        int $page,
        int $limit,
        ?string $search = null,
        string $sortBy = 'fullName',
        string $sortOrder = 'ASC',
        ?array $unrestrictedOrganizations = null
    ): array {
        if ($organizations === []) {
            return ['items' => [], 'total' => 0];
        }

        if ($unrestrictedOrganizations === null && $allowedCities !== null && $allowedCities === []) {
            return ['items' => [], 'total' => 0];
        }

        $allowedSortFields = ['fullName', 'companyName', 'phone', 'email', 'createdAt'];
        $sortBy = in_array($sortBy, $allowedSortFields, true) ? $sortBy : 'fullName';
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.deletedAt IS NULL')
            ->orderBy("t.$sortBy", $sortOrder);

        if ($unrestrictedOrganizations === null) {
            $qb->andWhere('t.organization IN (:organizations)')
                ->setParameter('organizations', $organizations);
        } else {
            $unrestrictedIds = array_map(static fn (Organization $organization): int => $organization->getId(), $unrestrictedOrganizations);
            $scopedOrganizations = array_values(array_filter(
                $organizations,
                static fn (Organization $organization): bool => !in_array($organization->getId(), $unrestrictedIds, true)
            ));
            if ($unrestrictedOrganizations === [] && $scopedOrganizations === []) {
                return ['items' => [], 'total' => 0];
            }

            if ($scopedOrganizations === [] || $allowedCities === []) {
                $qb->andWhere('t.organization IN (:fullAccessOrganizations)')
                    ->setParameter('fullAccessOrganizations', $unrestrictedOrganizations);
            } elseif ($unrestrictedOrganizations === []) {
                $qb->andWhere('t.organization IN (:cityScopedOrganizations) AND EXISTS (
                    SELECT 1
                    FROM App\\Entity\\Rental\\Lease l
                    JOIN App\\Entity\\Property\\Unit un WITH un = l.unit
                    JOIN App\\Entity\\Property\\Building b WITH b = un.building
                    JOIN App\\Entity\\Property\\Parcel p WITH p = b.parcel
                    WHERE l.tenant = t
                      AND l.deletedAt IS NULL
                      AND p.city IN (:allowedCities)
                )')
                    ->setParameter('cityScopedOrganizations', $scopedOrganizations)
                    ->setParameter('allowedCities', $allowedCities);
            } else {
                $qb->andWhere('(t.organization IN (:fullAccessOrganizations) OR (t.organization IN (:cityScopedOrganizations) AND EXISTS (
                    SELECT 1
                    FROM App\\Entity\\Rental\\Lease l
                    JOIN App\\Entity\\Property\\Unit un WITH un = l.unit
                    JOIN App\\Entity\\Property\\Building b WITH b = un.building
                    JOIN App\\Entity\\Property\\Parcel p WITH p = b.parcel
                    WHERE l.tenant = t
                      AND l.deletedAt IS NULL
                      AND p.city IN (:allowedCities)
                )))')
                    ->setParameter('fullAccessOrganizations', $unrestrictedOrganizations)
                    ->setParameter('cityScopedOrganizations', $scopedOrganizations)
                    ->setParameter('allowedCities', $allowedCities);
            }
        }

        if ($unrestrictedOrganizations === null && $allowedCities !== null) {
            $qb->andWhere(
                'EXISTS (
                    SELECT 1
                    FROM App\\Entity\\Rental\\Lease l
                    JOIN App\\Entity\\Property\\Unit un WITH un = l.unit
                    JOIN App\\Entity\\Property\\Building b WITH b = un.building
                    JOIN App\\Entity\\Property\\Parcel p WITH p = b.parcel
                    WHERE l.tenant = t
                      AND l.deletedAt IS NULL
                      AND p.city IN (:allowedCities)
                )'
            )
                ->setParameter('allowedCities', $allowedCities);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere(
                't.fullName LIKE :search OR t.companyName LIKE :search'
                . ' OR t.phone LIKE :search OR t.email LIKE :search'
            )
                ->setParameter('search', '%' . $search . '%');
        }

        return $this->fetchPaginated($qb, $page, $limit);
    }
}
