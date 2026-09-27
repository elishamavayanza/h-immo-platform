<?php

declare(strict_types=1);

namespace App\Repository\Identity;

use App\Entity\Identity\User;
use App\Entity\Identity\UserCity;
use App\Entity\Property\City;
use App\Repository\UuidParameterTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * UserCityRepository
 *
 * Package : Identity & Access
 * Entité  : App\Entity\Identity\UserCity
 *
 * Requêtes sur la table de liaison User <-> City, qui matérialise le
 * périmètre territorial d'un administrateur de ville (ADMIN_VILLE).
 *
 * C'est LA source de vérité de l'isolation par ville : une ville non
 * attribuée via cette table est hors de portée d'un ADMIN_VILLE, même
 * si elle appartient à son Organization.
 *
 * @extends ServiceEntityRepository<UserCity>
 */
class UserCityRepository extends ServiceEntityRepository
{
    use UuidParameterTrait;
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
     * Liste des identifiants des villes accessibles à un utilisateur.
     *
     * @return list<int>
     */
    public function findCityIdsForUser(User $user): array
    {
        $rows = $this->createQueryBuilder('uc')
            ->select('IDENTITY(uc.city) AS cityId')
            ->andWhere('uc.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['cityId'], $rows);
    }

    /**
     * Vérifie si un utilisateur a explicitement accès à une ville.
     */
    public function existsForUserAndCity(User $user, City $city): bool
    {
        return null !== $this->createQueryBuilder('uc')
            ->andWhere('uc.user = :user')
            ->andWhere('uc.city = :city')
            ->setParameter('user', $user)
            ->setParameter('city', $city)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste paginée des affectations de villes d'un utilisateur.
     *
     * @return array{items: list<UserCity>, total: int}
     */
    public function findPaginatedByUser(User $user, int $page, int $limit): array
    {
        $qb = $this->createQueryBuilder('uc')
            ->innerJoin('uc.city', 'c')
            ->addSelect('c')
            ->andWhere('uc.user = :user')
            ->setParameter('user', $user)
            ->orderBy('uc.createdAt', 'DESC')
            ->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit);

        $items = $qb->getQuery()->getResult();

        $countQb = $this->createQueryBuilder('uc')
            ->select('COUNT(uc.id)')
            ->andWhere('uc.user = :user')
            ->setParameter('user', $user);

        return [
            'items' => $items,
            'total' => (int) $countQb->getQuery()->getSingleScalarResult(),
        ];
    }
}
