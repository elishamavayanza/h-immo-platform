<?php

declare(strict_types=1);

namespace App\Repository\Identity;

use App\Entity\Identity\OrganizationUser;
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
     */
    public function findOneByOrganizationAndUser(int $organizationId, int $userId): ?OrganizationUser
    {
        return $this->createQueryBuilder('ou')
            ->andWhere('ou.organization = :organizationId')
            ->andWhere('ou.user = :userId')
            ->setParameter('organizationId', $organizationId)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste tous les rattachements (avec rôle) pour une organisation.
     *
     * @return OrganizationUser[]
     */
    public function findByOrganization(int $organizationId): array
    {
        return $this->createQueryBuilder('ou')
            ->andWhere('ou.organization = :organizationId')
            ->setParameter('organizationId', $organizationId)
            ->getQuery()
            ->getResult();
    }
}
