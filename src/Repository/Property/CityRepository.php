<?php

declare(strict_types=1);

namespace App\Repository\Property;

use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Entity\Property\City;
use App\Enum\CityStatus;
use App\Repository\PaginatedResultTrait;
use App\Repository\UuidParameterTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * CityRepository
 *
 * Package : Property Management
 * Entité  : App\Entity\Property\City
 *
 * Toute méthode de liste de ce repository reçoit soit une liste
 * d'Organizations, soit une liste de villes déjà autorisées : aucune
 * requête ne renvoie le patrimoine d'un tenant par défaut. Le filtrage
 * par périmètre est décidé par `SecurityService`, jamais par le client.
 *
 * @extends ServiceEntityRepository<City>
 */
class CityRepository extends ServiceEntityRepository
{
    use PaginatedResultTrait;
    use UuidParameterTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, City::class);
    }

    public function save(City $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(City $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByUuid(Uuid $uuid): ?City
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.uuid = :uuid')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('uuid', $this->bindableUuid($uuid))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche d'une ville par son code, à l'intérieur d'une organisation
     * (le code n'est unique que par organisation, pas globalement).
     */
    public function findOneByOrganizationAndCode(Organization $organization, string $code): ?City
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.organization = :organization')
            ->andWhere('c.code = :code')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('organization', $organization)
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste toutes les villes actives d'une organisation.
     *
     * @return City[]
     */
    public function findActiveByOrganization(Organization $organization): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.organization = :organization')
            ->andWhere('c.status = :status')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('organization', $organization)
            ->setParameter('status', CityStatus::ACTIVE)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Toutes les villes actives de la plateforme.
     *
     * Réservée à l'administration de la plateforme (SUPER_ADMIN), qui
     * n'est pas borné à une organization. Tout autre appelant doit
     * passer par une liste de villes déjà filtrée.
     *
     * @return list<City>
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.status = :status')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('status', CityStatus::ACTIVE)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Villes explicitement attribuées à un utilisateur, triées par nom.
     *
     * La méthode vit ici, et non dans `UserCityRepository`, parce que
     * `ServiceEntityRepository::createQueryBuilder()` ancre toujours la
     * requête sur la classe gérée par le dépôt : depuis `UserCity`, aucune
     * requête ne peut hydrater des `City`. Le lien est donc filtré par un
     * sous-requête `EXISTS`.
     *
     * @return list<City>
     */
    public function findAssignedToUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.deletedAt IS NULL')
            ->andWhere(
                'EXISTS (
                    SELECT 1
                    FROM App\Entity\Identity\UserCity uc
                    WHERE uc.city = c AND uc.user = :user
                )'
            )
                ->setParameter('user', $user)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Villes explicitement attribuées à un utilisateur DANS UNE ORGANISATION.
     *
     * Similaire à `findAssignedToUser` mais borné à une organization.
     * Nécessaire pour distinguer les villes d'un ADMIN_VILLE par org.
     *
     * @return list<City>
     */
    public function findAssignedToUserInOrganization(User $user, Organization $organization): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.organization = :organization')
            ->andWhere('c.deletedAt IS NULL')
            ->andWhere(
                'EXISTS (
                    SELECT 1
                    FROM App\Entity\Identity\UserCity uc
                    WHERE uc.city = c AND uc.user = :user
                )'
            )
            ->setParameter('user', $user)
            ->setParameter('organization', $organization)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Toutes les villes d'une organization, sans filtre de statut.
     *
     * Utilisé pour étendre le périmètre d'un PATRON ou d'un
     * ADMIN_IMMOBILIER à l'ensemble de ses villes.
     *
     * @return list<City>
     */
    public function findInOrganization(Organization $organization): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.organization = :organization')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('organization', $organization)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste paginée des villes accessibles.
     *
     * @param list<Organization> $organizations Organizations du lecteur
     * @param list<City>|null   $allowedCities villes autorisées (ADMIN_VILLE)
     *                                        `null` = pas de filtre ville
     * @return array{items: list<City>, total: int}
     */
    public function findPaginatedAccessible(
        array $organizations,
        ?array $allowedCities,
        int $page,
        int $limit,
        ?string $search = null
    ): array {
        if ($organizations === []) {
            return ['items' => [], 'total' => 0];
        }

        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.deletedAt IS NULL')
            ->andWhere('c.organization IN (:organizations)')
            ->setParameter('organizations', $organizations)
            ->orderBy('c.name', 'ASC');

        if ($allowedCities !== null) {
            if ($allowedCities === []) {
                return ['items' => [], 'total' => 0];
            }

            $qb->andWhere('c IN (:allowedCities)')
                ->setParameter('allowedCities', $allowedCities);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('c.name LIKE :search OR c.code LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $this->fetchPaginated($qb, $page, $limit);
    }
}
