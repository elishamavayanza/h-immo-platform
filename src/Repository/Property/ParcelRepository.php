<?php

declare(strict_types=1);

namespace App\Repository\Property;

use App\Entity\Property\City;
use App\Entity\Property\Parcel;
use App\Repository\PaginatedResultTrait;
use App\Repository\UuidParameterTrait;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * ParcelRepository
 *
 * Package : Property Management
 * Entité  : App\Entity\Property\Parcel
 *
 * La parcelle est toujours rattachée à une ville, elle-même rattachée à
 * une organisation : le périmètre se calcule donc à partir de la liste
 * de villes autorisées. Aucune méthode de liste ne reçoit d'identifiant
 * de ville « libre » venant du client.
 *
 * @extends ServiceEntityRepository<Parcel>
 */
class ParcelRepository extends ServiceEntityRepository
{
    use PaginatedResultTrait;
    use UuidParameterTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Parcel::class);
    }

    public function save(Parcel $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Parcel $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByUuid(Uuid $uuid): ?Parcel
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.uuid = :uuid')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('uuid', $this->bindableUuid($uuid))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche une parcelle par sa référence, au sein d'une ville.
     */
    public function findOneByCityAndReference(City $city, string $reference): ?Parcel
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.city = :city')
            ->andWhere('p.reference = :reference')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('city', $city)
            ->setParameter('reference', $reference)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste toutes les parcelles d'une ville.
     *
     * @return Parcel[]
     */
    public function findByCity(City $city): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.city = :city')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('city', $city)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste paginée des parcelles accessibles.
     *
     * @param list<City> $cities villes autorisées pour le lecteur
     * @return array{items: list<Parcel>, total: int}
     */
    public function findPaginatedAccessible(array $cities, int $page, int $limit, ?string $search = null): array
    {
        if ($cities === []) {
            return ['items' => [], 'total' => 0];
        }

        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.city IN (:cities)')
            ->setParameter('cities', $cities)
            ->orderBy('p.name', 'ASC');

        if ($search !== null && $search !== '') {
            $qb->andWhere('p.name LIKE :search OR p.reference LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $this->fetchPaginated($qb, $page, $limit);
    }
}
