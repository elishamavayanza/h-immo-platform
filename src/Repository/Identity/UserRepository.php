<?php

declare(strict_types=1);

namespace App\Repository\Identity;

use App\Entity\Identity\User;
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
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste tous les utilisateurs rattachés à une Organization donnée,
     * via la table de liaison OrganizationUser.
     *
     * @return User[]
     */
    public function findByOrganization(int $organizationId): array
    {
        return $this->createQueryBuilder('u')
            ->innerJoin('App\Entity\Identity\OrganizationUser', 'ou', 'WITH', 'ou.user = u')
            ->andWhere('ou.organization = :organizationId')
            ->andWhere('u.deletedAt IS NULL')
            ->setParameter('organizationId', $organizationId)
            ->getQuery()
            ->getResult();
    }
}
