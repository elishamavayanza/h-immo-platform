<?php

declare(strict_types=1);

namespace App\Repository\Identity;

use App\Entity\Identity\UserCity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * UserCityRepository
 *
 * Package : Identity & Access
 * Entité  : App\Entity\Identity\UserCity
 *
 * Requêtes sur la table de liaison User <-> City, utilisée pour
 * restreindre le périmètre des utilisateurs ADMIN_VILLE.
 *
 * @extends ServiceEntityRepository<UserCity>
 */
class UserCityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserCity::class);
    }

    public function save(UserCity $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserCity $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Liste les identifiants des villes accessibles à un utilisateur.
     *
     * @return int[]
     */
    public function findCityIdsForUser(int $userId): array
    {
        $rows = $this->createQueryBuilder('uc')
            ->select('IDENTITY(uc.city) AS cityId')
            ->andWhere('uc.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['cityId'], $rows);
    }

    /**
     * Vérifie si un utilisateur a explicitement accès à une ville.
     */
    public function existsForUserAndCity(int $userId, int $cityId): bool
    {
        return null !== $this->createQueryBuilder('uc')
            ->andWhere('uc.user = :userId')
            ->andWhere('uc.city = :cityId')
            ->setParameter('userId', $userId)
            ->setParameter('cityId', $cityId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
