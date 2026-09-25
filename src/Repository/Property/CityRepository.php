<?php

declare(strict_types=1);

namespace App\Repository\Property;

use App\Entity\Property\City;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * CityRepository
 *
 * Package : Property Management
 * Entité  : App\Entity\Property\City
 *
 * @extends ServiceEntityRepository<City>
 */
class CityRepository extends ServiceEntityRepository
{
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
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche une ville par son code, à l'intérieur d'une organisation
     * (le code n'est unique que par organisation, pas globalement).
     */
    public function findOneByOrganizationAndCode(int $organizationId, string $code): ?City
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.organization = :organizationId')
            ->andWhere('c.code = :code')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('organizationId', $organizationId)
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste toutes les villes actives d'une organisation.
     *
     * @return City[]
     */
    public function findActiveByOrganization(int $organizationId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.organization = :organizationId')
            ->andWhere('c.status = :status')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('organizationId', $organizationId)
            ->setParameter('status', \App\Enum\CityStatus::ACTIVE)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
