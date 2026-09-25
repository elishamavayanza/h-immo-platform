<?php

declare(strict_types=1);

namespace App\Repository\Property;

use App\Entity\Property\Parcel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * ParcelRepository
 *
 * Package : Property Management
 * Entité  : App\Entity\Property\Parcel
 *
 * @extends ServiceEntityRepository<Parcel>
 */
class ParcelRepository extends ServiceEntityRepository
{
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
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche une parcelle par sa référence, au sein d'une ville.
     */
    public function findOneByCityAndReference(int $cityId, string $reference): ?Parcel
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.city = :cityId')
            ->andWhere('p.reference = :reference')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('cityId', $cityId)
            ->setParameter('reference', $reference)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste toutes les parcelles d'une ville.
     *
     * @return Parcel[]
     */
    public function findByCity(int $cityId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.city = :cityId')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('cityId', $cityId)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
