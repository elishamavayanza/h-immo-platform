<?php

declare(strict_types=1);

namespace App\Dto\Response\System;

use App\Entity\System\AuditLog;
use OpenApi\Attributes as OA;

/**
 * AuditLogResponse
 *
 * Package : System & Audit — DTO de réponse
 *
 * Représentation publique d'une entrée du journal d'audit.
 * `organizationId`/`userId` sont nullables pour couvrir les actions
 * techniques (cf. AuditLog).
 */
#[OA\Schema(
    title: 'AuditLogResponse',
    description: 'Représentation publique d\'une entrée du journal d\'audit système.'
)]
final readonly class AuditLogResponse
{
    public function __construct(
        #[OA\Property(description: 'UUID public de l\'entrée du journal d\'audit', format: 'uuid', example: 'f47ac10b-58cc-4372-a567-0e02b2c3d4e5')]
        public string $id,

        #[OA\Property(description: 'UUID public de l\'organisation rattachée', format: 'uuid', example: '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d', nullable: true)]
        public ?string $organizationId,

        #[OA\Property(description: 'UUID public de l\'utilisateur déclencheur', format: 'uuid', example: 'd5e6f7a8-b9c0-1d2e-3f4a-5b6c7d8e9f0a', nullable: true)]
        public ?string $userId,

        #[OA\Property(description: 'Action exécutée', example: 'UPDATE')]
        public string $action,

        #[OA\Property(description: 'Nom FQCN ou identifiant de l\'entité impactée', example: 'App\\Entity\\Rental\\Lease')]
        public string $entityType,

        #[OA\Property(description: 'Identifiant interne (ID) de l\'entité impactée', example: 42)]
        public int $entityId,

        #[OA\Property(
            description: 'Clés/valeurs des données avant modification',
            type: 'object',
            example: ['monthlyRent' => '400.00', 'status' => 'DRAFT'],
            nullable: true
        )]
        public ?array $oldValues,

        #[OA\Property(
            description: 'Clés/valeurs des données après modification',
            type: 'object',
            example: ['monthlyRent' => '450.00', 'status' => 'ACTIVE'],
            nullable: true
        )]
        public ?array $newValues,

        #[OA\Property(description: 'Horodatage de l\'action enregistrée', format: 'date-time', example: '2026-03-02T10:15:30Z')]
        public \DateTimeImmutable $createdAt,
    ) {
    }

    public static function fromEntity(AuditLog $auditLog): self
    {
        return new self(
            id: (string) $auditLog->getUuid(),
            organizationId: $auditLog->getOrganization()?->getUuid() !== null
                ? (string) $auditLog->getOrganization()->getUuid()
                : null,
            userId: $auditLog->getUser()?->getUuid() !== null
                ? (string) $auditLog->getUser()->getUuid()
                : null,
            action: $auditLog->getAction(),
            entityType: $auditLog->getEntityType(),
            entityId: $auditLog->getEntityId(),
            oldValues: $auditLog->getOldValues(),
            newValues: $auditLog->getNewValues(),
            createdAt: $auditLog->getCreatedAt(),
        );
    }
}
