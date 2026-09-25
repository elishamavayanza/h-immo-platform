<?php

declare(strict_types=1);

namespace App\Repository\Property;

use App\Entity\Property\Building;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * BuildingRepository
 *
 * Package : Property Management
 * Entité  : App\Entity\Property\Building
 *
 * @extends ServiceEntityRepository<Building>
 */
class BuildingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Building::class);
    }

    public function save(Building $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Building $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByUuid(Uuid $uuid): ?Building
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.uuid = :uuid')
            ->andWhere('b.deletedAt IS NULL')
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche un bâtiment par sa référence, au sein d'une parcelle.
     */
    public function findOneByParcelAndReference(int $parcelId, string $reference): ?Building
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.parcel = :parcelId')
            ->andWhere('b.reference = :reference')
            ->andWhere('b.deletedAt IS NULL')
            ->setParameter('parcelId', $parcelId)
            ->setParameter('reference', $reference)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste tous les bâtiments d'une parcelle.
     *
     * @return Building[]
     */
    public function findByParcel(int $parcelId): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.parcel = :parcelId')
            ->andWhere('b.deletedAt IS NULL')
            ->setParameter('parcelId', $parcelId)
            ->orderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
