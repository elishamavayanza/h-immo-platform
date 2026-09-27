<?php

declare(strict_types=1);

namespace App\Repository\Identity;

use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Repository\PaginatedResultTrait;
use App\Repository\UuidParameterTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * UserRepository
 *
 * Package : Identity & Access
 * Entité  : App\Entity\Identity\User
 *
 * Fournit les requêtes d'accès aux utilisateurs de la plateforme.
 * Ne contient aucune règle métier (autorisation, hachage de mot de
 * passe, etc.) : uniquement de la lecture/écriture de persistance.
 *
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    use PaginatedResultTrait;
    use UuidParameterTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Persiste l'entité (et déclenche le flush si demandé).
     */
    public function save(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime l'entité (et déclenche le flush si demandé).
     */
    public function remove(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Recherche un utilisateur actif par son adresse email.
     * Utilisé typiquement pour l'authentification.
     */
    public function findOneActiveByEmail(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.email = :email')
            ->andWhere('u.isActive = true')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche un utilisateur par son UUID public (non supprimé).
     */
    public function findOneByUuid(Uuid $uuid): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.uuid = :uuid')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('uuid', $this->bindableUuid($uuid))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste tous les utilisateurs rattachés à une Organization donnée,
     * via la table de liaison OrganizationUser.
     *
     * @return User[]
     */
    public function findByOrganization(Organization $organization): array
    {
        return $this->createQueryBuilder('u')
            ->innerJoin('App\Entity\Identity\OrganizationUser', 'ou', 'WITH', 'ou.user = u')
            ->addSelect('ou')
            ->andWhere('ou.organization = :organization')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('organization', $organization)
            ->orderBy('u.fullName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Identifiants des utilisateurs rattachés à une Organization.
     *
     * Utilisé pour borner une liste d'utilisateurs à l'Organization
     * courante sans charger les entités hors périmètre.
     *
     * @return list<int>
     */
    public function findIdsByOrganization(Organization $organization): array
    {
        $rows = $this->createQueryBuilder('u')
            // `IDENTITY()` attend un chemin d'association « to one », pas
            // l'alias racine : `IDENTITY(u)` est rejeté par le DQL
            // (« Must be a SingleValuedAssociationField »). Pour l'entité
            // racine, on prend simplement sa clé primaire.
            ->select('u.id AS userId')
            ->innerJoin('App\Entity\Identity\OrganizationUser', 'ou', 'WITH', 'ou.user = u')
            ->andWhere('ou.organization = :organization')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('organization', $organization)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['userId'], $rows);
    }

    /**
     * Liste paginée des utilisateurs d'une ou plusieurs Organizations.
     *
     * Une liste vide retourne un résultat vide : un utilisateur
     * n'appartenant à aucune Organization ne voit aucun compte.
     *
     * @param list<Organization> $organizations
     * @return array{items: list<User>, total: int}
     */
    public function findPaginatedByOrganizations(
        array $organizations,
        int $page,
        int $limit,
        ?string $search = null
    ): array {
        if ($organizations === []) {
            return ['items' => [], 'total' => 0];
        }

        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.deletedAt IS NULL')
            ->andWhere('u.id IN (:userIds)')
            ->setParameter('userIds', $this->resolveUserIds($organizations))
            ->orderBy('u.fullName', 'ASC');

        if ($search !== null && $search !== '') {
            $qb->andWhere('u.fullName LIKE :search OR u.email LIKE :search OR u.phone LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $this->fetchPaginated($qb, $page, $limit);
    }

    /**
     * Liste paginée de tous les utilisateurs de la plateforme.
     *
     * ⚠ Réservée à l'administration de la plateforme (SUPER_ADMIN) :
     * aucun filtre de tenancy n'est appliqué ici.
     *
     * @return array{items: list<User>, total: int}
     */
    public function findPaginatedAll(int $page, int $limit, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.deletedAt IS NULL')
            ->orderBy('u.fullName', 'ASC');

        if ($search !== null && $search !== '') {
            $qb->andWhere('u.fullName LIKE :search OR u.email LIKE :search OR u.phone LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $this->fetchPaginated($qb, $page, $limit);
    }

    /**
     * @param list<Organization> $organizations
     * @return list<int>
     */
    private function resolveUserIds(array $organizations): array
    {
        $ids = [];

        foreach ($organizations as $organization) {
            foreach ($this->findIdsByOrganization($organization) as $id) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }
}
