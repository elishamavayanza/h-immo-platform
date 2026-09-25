<?php

declare(strict_types=1);

namespace App\Repository\Rental;

use App\Entity\Rental\Lease;
use App\Enum\LeaseStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * LeaseRepository
 *
 * Package : Rental Management
 * Entité  : App\Entity\Rental\Lease
 *
 * Fournit notamment la requête utilisée par la couche service pour
 * faire respecter la règle métier « une Unit ne peut avoir qu'un seul
 * Lease ACTIVE à la fois » — la règle elle-même reste hors de
 * l'entité et hors du repository (elle appartient à un service).
 *
 * @extends ServiceEntityRepository<Lease>
 */
class LeaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lease::class);
    }

    public function save(Lease $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Lease $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByUuid(Uuid $uuid): ?Lease
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.uuid = :uuid')
            ->andWhere('l.deletedAt IS NULL')
            ->setParameter('uuid', $uuid)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Recherche un bail par sa référence, au sein d'une organisation.
     */
    public function findOneByOrganizationAndReference(int $organizationId, string $reference): ?Lease
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.organization = :organizationId')
            ->andWhere('l.reference = :reference')
            ->andWhere('l.deletedAt IS NULL')
            ->setParameter('organizationId', $organizationId)
            ->setParameter('reference', $reference)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retourne le bail actuellement ACTIVE d'une Unit, s'il existe.
     * Point d'entrée clé pour la règle « un seul bail actif par Unit ».
     */
    public function findActiveLeaseForUnit(int $unitId): ?Lease
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.unit = :unitId')
            ->andWhere('l.status = :status')
            ->andWhere('l.deletedAt IS NULL')
            ->setParameter('unitId', $unitId)
            ->setParameter('status', LeaseStatus::ACTIVE)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste l'historique complet des baux d'une Unit (tous statuts).
     *
     * @return Lease[]
     */
    public function findHistoryByUnit(int $unitId): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.unit = :unitId')
            ->setParameter('unitId', $unitId)
            ->orderBy('l.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
