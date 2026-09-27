<?php

declare(strict_types=1);

namespace App\Dto\Request\System;

use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'AuditLogFilterDto',
    description: 'Critères de filtrage pour la recherche dans le journal d\'audit.'
)]
final class AuditLogFilterDto
{
    public function __construct(
        #[OA\Property(description: 'UUID de l\'organisation', format: 'uuid', nullable: true)]
        public ?string $organizationUuid = null,

        #[OA\Property(description: 'UUID de l\'utilisateur déclencheur', format: 'uuid', nullable: true)]
        public ?string $userUuid = null,

        #[OA\Property(description: 'Action effectuée (ex: CREATE, UPDATE, DELETE)', example: 'UPDATE', nullable: true)]
        public ?string $action = null,

        #[OA\Property(description: 'Type d\'entité (FQCN ou nom)', example: 'App\\Entity\\Rental\\Lease', nullable: true)]
        public ?string $entityType = null,

        #[OA\Property(description: 'ID interne de l\'entité cible', example: 42, nullable: true)]
        public ?int $entityId = null,

        #[OA\Property(description: 'Date de début de recherche', format: 'date-time', nullable: true)]
        public ?\DateTimeImmutable $from = null,

        #[OA\Property(description: 'Date de fin de recherche', format: 'date-time', nullable: true)]
        public ?\DateTimeImmutable $to = null,

        #[OA\Property(description: 'Numéro de page', default: 1)]
        public int $page = 1,

        #[OA\Property(description: 'Nombre d\'éléments par page', default: 20)]
        public int $itemsPerPage = 20,
    ) {
    }
}
