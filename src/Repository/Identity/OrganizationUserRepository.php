<?php

declare(strict_types=1);

namespace App\Repository\Identity;

use App\Entity\Identity\Organization;
use App\Entity\Identity\OrganizationUser;
use App\Entity\Identity\User;
use App\Enum\OrganizationRole;
use App\Repository\UuidParameterTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * OrganizationUserRepository
 *
 * Package : Identity & Access
 * Entité  : App\Entity\Identity\OrganizationUser
 *
 * Requêtes sur la table de liaison User <-> Organization portant le
 * rôle (OrganizationRole) de chaque utilisateur.
 *
 * @extends ServiceEntityRepository<OrganizationUser>
 */
class OrganizationUserRepository extends ServiceEntityRepository
{
    use UuidParameterTrait;
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrganizationUser::class);
    }

    public function save(OrganizationUser $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(OrganizationUser $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Recherche le rattachement d'un utilisateur précis à une
     * organisation précise (garantit l'unicité du couple).
     *
     * Paramètres typés par entité : le passing de l'identifiant
     * interne exposait à un mélange d'identifiants entre deux
     * Organizations lors de l'appel depuis un contexte multi-tenant.
     */
    public function findOneByOrganizationAndUser(Organization $organization, User $user): ?OrganizationUser
    {
        return $this->createQueryBuilder('ou')
            ->andWhere('ou.organization = :organization')
            ->andWhere('ou.user = :user')
            ->setParameter('organization', $organization)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste tous les rattachements (avec rôle) pour une organisation.
     *
     * @return OrganizationUser[]
     */
    public function findByOrganization(Organization $organization): array
    {
        return $this->createQueryBuilder('ou')
            ->andWhere('ou.organization = :organization')
            ->setParameter('organization', $organization)
            ->orderBy('ou.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Tous les rattachements d'un utilisateur, avec son rôle.
     *
     * Source de vérité du périmètre « multi-Organization » :
     * elle détermine les Organizations visibles par l'utilisateur.
     *
     * @return OrganizationUser[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('ou')
            ->innerJoin('ou.organization', 'o')
            ->addSelect('o')
            ->andWhere('ou.user = :user')
            ->andWhere('o.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('ou.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Rattachements d'un utilisateur restreints à un rôle donné.
     *
     * @return OrganizationUser[]
     */
    public function findByUserAndRole(User $user, OrganizationRole $role): array
    {
        return $this->createQueryBuilder('ou')
            ->andWhere('ou.user = :user')
            ->andWhere('ou.role = :role')
            ->setParameter('user', $user)
            ->setParameter('role', $role)
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste paginée des rattachements d'une organisation.
     *
     * @return array{items: list<OrganizationUser>, total: int}
     */
    public function findPaginatedByOrganization(Organization $organization, int $page, int $limit): array
    {
        $qb = $this->createQueryBuilder('ou')
            ->innerJoin('ou.user', 'u')
            ->addSelect('u')
            ->andWhere('ou.organization = :organization')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('organization', $organization)
            ->orderBy('ou.createdAt', 'DESC')
            ->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit);

        $items = $qb->getQuery()->getResult();

        $countQb = $this->createQueryBuilder('ou')
            ->select('COUNT(ou.id)')
            ->innerJoin('ou.user', 'u')
            ->andWhere('ou.organization = :organization')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('organization', $organization);

        return [
            'items' => $items,
            'total' => (int) $countQb->getQuery()->getSingleScalarResult(),
        ];
    }
}
