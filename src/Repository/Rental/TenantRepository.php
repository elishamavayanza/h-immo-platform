<?php

declare(strict_types=1);

namespace App\Repository\Rental;

use App\Entity\Rental\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * TenantRepository
 *
 * Package : Rental Management
 * Entité  : App\Entity\Rental\Tenant
 *
 * @extends ServiceEntityRepository<Tenant>
 */
class TenantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tenant::class);
    }

    public function save(Tenant $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Tenant $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByUuid(Uuid $uuid): ?Tenant
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.uuid = :uuid')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche des locataires d'une organisation par téléphone ou email
     * (recherche simple, utile pour éviter les doublons à la saisie).
     *
     * @return Tenant[]
     */
    public function searchByOrganization(int $organizationId, string $term): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.organization = :organizationId')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere('t.phone LIKE :term OR t.email LIKE :term OR t.lastName LIKE :term OR t.companyName LIKE :term')
            ->setParameter('organizationId', $organizationId)
            ->setParameter('term', '%' . $term . '%')
            ->getQuery()
            ->getResult();
    }
}
