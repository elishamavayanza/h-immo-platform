<?php

declare(strict_types=1);

namespace App\Repository\Identity;

use App\Entity\Identity\Organization;
use App\Enum\OrganizationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * OrganizationRepository
 *
 * Package : Identity & Access
 * Entité  : App\Entity\Identity\Organization
 *
 * Requêtes d'accès aux organisations clientes (tenants) de la
 * plateforme Soft-IMMO.
 *
 * @extends ServiceEntityRepository<Organization>
 */
class OrganizationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Organization::class);
    }

    public function save(Organization $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Organization $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Recherche une organisation par son code unique.
     */
    public function findOneByCode(string $code): ?Organization
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.code = :code')
            ->andWhere('o.deletedAt IS NULL')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByUuid(Uuid $uuid): ?Organization
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.uuid = :uuid')
            ->andWhere('o.deletedAt IS NULL')
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste toutes les organisations actives.
     *
     * @return Organization[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.status = :status')
            ->andWhere('o.deletedAt IS NULL')
            ->setParameter('status', OrganizationStatus::ACTIVE)
            ->orderBy('o.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
