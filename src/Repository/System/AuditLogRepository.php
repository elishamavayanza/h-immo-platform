<?php

declare(strict_types=1);

namespace App\Repository\System;

use App\Entity\Identity\Organization;
use App\Entity\System\AuditLog;
use App\Repository\PaginatedResultTrait;
use App\Repository\UuidParameterTrait;
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
    use PaginatedResultTrait;
    use UuidParameterTrait;

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
        Organization $organization,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to
    ): array {
        return $this->createQueryBuilder('a')
            ->andWhere('a.organization = :organization')
            ->andWhere('a.createdAt BETWEEN :from AND :to')
            ->setParameter('organization', $organization)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Requête de consultation paginée des journaux d'audit.
     *
     * Tous les critères sont facultatifs : une clause n'est ajoutée que
     * pour un filtre effectivement fourni, afin d'éviter des critères
     * `x = NULL`, qui ne correspondraient jamais.
     *
     * ⚠ `organization` est le premier critère de portée. Le journal
     * d'audit contient des données sensibles (valeurs avant/après) : il
     * ne doit jamais être servi sans avoir déjà validé, via
     * `SecurityService`, que l'appelant est autorisé sur ce périmètre.
     *
     * @return array{items: list<AuditLog>, total: int}
     */
    public function findByFilter(
        ?Organization $organization = null,
        ?string $action = null,
        ?string $entityType = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
        int $page = 1,
        int $limit = 50,
        ?array $organizations = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC');

        if ($organization !== null) {
            $qb->andWhere('a.organization = :organization')
                ->setParameter('organization', $organization);
        } elseif ($organizations !== null) {
            // Liste vide = aucun journal accessible. Sans ce cas, une
            // liste vide serait indistinguishable d'aucun filtre et
            // l'appelant recevrait les journaux de tous les tenants.
            if ($organizations === []) {
                return ['items' => [], 'total' => 0];
            }

            $qb->andWhere('a.organization IN (:organizations)')
                ->setParameter('organizations', $organizations);
        }

        if ($action !== null && $action !== '') {
            $qb->andWhere('a.action = :action')
                ->setParameter('action', $action);
        }

        if ($entityType !== null && $entityType !== '') {
            $qb->andWhere('a.entityType = :entityType')
                ->setParameter('entityType', $entityType);
        }

        if ($from !== null) {
            $qb->andWhere('a.createdAt >= :from')
                ->setParameter('from', $from);
        }

        if ($to !== null) {
            $qb->andWhere('a.createdAt <= :to')
                ->setParameter('to', $to);
        }

        return $this->fetchPaginated($qb, $page, $limit);
    }
}
