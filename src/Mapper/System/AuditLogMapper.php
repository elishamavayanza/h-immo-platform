<?php

declare(strict_types=1);

namespace App\Mapper\System;

use App\Dto\Response\System\AuditLogResponse;
use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Entity\System\AuditLog;

final class AuditLogMapper
{
    public function toResponse(AuditLog $auditLog): AuditLogResponse
    {
        return AuditLogResponse::fromEntity($auditLog);
    }

    /**
     * @param array<AuditLog> $entities
     * @return array<AuditLogResponse>
     */
    public function toResponseCollection(array $entities): array
    {
        return array_map(fn (AuditLog $entity) => $this->toResponse($entity), $entities);
    }

    /**
     * Crée une nouvelle instance de log prête à être enregistrée.
     */
    public function createEntity(
        string $action,
        string $entityType,
        int $entityId,
        ?Organization $organization = null,
        ?User $user = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): AuditLog {
        $log = new AuditLog();
        $log->setAction($action);
        $log->setEntityType($entityType);
        $log->setEntityId($entityId);
        $log->setOrganization($organization);
        $log->setUser($user);
        $log->setOldValues($oldValues);
        $log->setNewValues($newValues);

        return $log;
    }
}
