<?php

declare(strict_types=1);

namespace App\Service\System;

use App\Dto\Request\System\AuditLogFilterDto;
use App\Dto\Response\System\AuditLogResponse;
use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Entity\System\AuditLog;
use App\Mapper\System\AuditLogMapper;
use App\Repository\System\AuditLogRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AuditLogService
{
    public function __construct(
        private readonly AuditLogRepository $auditLogRepository,
        private readonly AuditLogMapper $mapper,
    ) {
    }

    /**
     * Enregistre un nouvel événement d'audit dans le système.
     */
    public function log(
        string $action,
        string $entityType,
        int $entityId,
        ?Organization $organization = null,
        ?User $user = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): AuditLogResponse {
        $auditLog = $this->mapper->createEntity(
            action: $action,
            entityType: $entityType,
            entityId: $entityId,
            organization: $organization,
            user: $user,
            oldValues: $oldValues,
            newValues: $newValues
        );

        $this->auditLogRepository->save($auditLog, flush: true);

        return $this->mapper->toResponse($auditLog);
    }

    /**
     * Recherche paginée de logs d'audit.
     *
     * @return array{items: array<AuditLogResponse>, total: int, page: int, pages: int}
     */
    public function getPaginatedLogs(AuditLogFilterDto $filter): array
    {
        $paginator = $this->auditLogRepository->findByFilter($filter);
        $totalItems = count($paginator);
        $pagesCount = (int) ceil($totalItems / $filter->itemsPerPage);

        /** @var array<AuditLog> $items */
        $items = iterator_to_array($paginator);

        return [
            'items' => $this->mapper->toResponseCollection($items),
            'total' => $totalItems,
            'page' => $filter->page,
            'pages' => max(1, $pagesCount),
        ];
    }

    /**
     * Récupère un log d'audit par son UUID.
     */
    public function getByUuid(string $uuid): AuditLogResponse
    {
        /** @var AuditLog|null $auditLog */
        $auditLog = $this->auditLogRepository->findOneBy(['uuid' => $uuid]);

        if (!$auditLog) {
            throw new NotFoundHttpException(sprintf('Audit log with UUID "%s" not found.', $uuid));
        }

        return $this->mapper->toResponse($auditLog);
    }
}
