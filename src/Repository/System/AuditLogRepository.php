<?php

declare(strict_types=1);

namespace App\Repository\System;

use App\Entity\System\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * AuditLogRepository
 *
 * Package : System & Audit
 * Entité  : App\Entity\System\AuditLog
 *
 * Le journal d'audit est en écriture seule côté métier : ce repository
 * n'expose donc pas de méthode `remove()`, conformément à
 * CreatedOnlyEntity (un log ne doit jamais être supprimé).
 *
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function save(AuditLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Liste les entrées d'audit concernant une entité métier précise
     * (ex. entityType = 'Lease', entityId = 42), triées du plus récent
     * au plus ancien.
     *
     * @return AuditLog[]
     */
    public function findByEntity(string $entityType, int $entityId): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.entityType = :entityType')
            ->andWhere('a.entityId = :entityId')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste les entrées d'audit d'une organisation sur une fenêtre
     * temporelle donnée.
     *
     * @return AuditLog[]
     */
    public function findByOrganizationBetween(
        int $organizationId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to
    ): array {
        return $this->createQueryBuilder('a')
            ->andWhere('a.organization = :organizationId')
            ->andWhere('a.createdAt BETWEEN :from AND :to')
            ->setParameter('organizationId', $organizationId)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
