<?php

declare(strict_types=1);

namespace App\Controller\System;

use App\Dto\Request\System\AuditLogFilterDto;
use App\Dto\Response\System\AuditLogResponse;
use App\Service\System\AuditLogService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/system/audit-logs')]
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: 'System & Audit')]
final class AuditLogController extends AbstractController
{
    public function __construct(
        private readonly AuditLogService $auditLogService
    ) {
    }

    #[Route('', name: 'app_system_audit_logs_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/system/audit-logs',
        summary: 'Lister les entrées du journal d\'audit avec filtres et pagination',
        parameters: [
            new OA\Parameter(name: 'organizationUuid', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'userUuid', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'action', in: 'query', schema: new OA\Schema(type: 'string', example: 'UPDATE')),
            new OA\Parameter(name: 'entityType', in: 'query', schema: new OA\Schema(type: 'string', example: 'App\\Entity\\Rental\\Lease')),
            new OA\Parameter(name: 'entityId', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date-time')),
            new OA\Parameter(name: 'to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date-time')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'itemsPerPage', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste paginée des entrées d\'audit',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: AuditLogResponse::class)),
                        new OA\Property(property: 'total', type: 'integer', example: 120),
                        new OA\Property(property: 'page', type: 'integer', example: 1),
                        new OA\Property(property: 'pages', type: 'integer', example: 6),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Non authentifié'),
            new OA\Response(response: 403, description: 'Accès refusé'),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        $filter = new AuditLogFilterDto(
            organizationUuid: $request->query->get('organizationUuid'),
            userUuid: $request->query->get('userUuid'),
            action: $request->query->get('action'),
            entityType: $request->query->get('entityType'),
            entityId: $request->query->has('entityId') ? (int) $request->query->get('entityId') : null,
            from: $request->query->has('from') ? new \DateTimeImmutable($request->query->get('from')) : null,
            to: $request->query->has('to') ? new \DateTimeImmutable($request->query->get('to')) : null,
            page: max(1, $request->query->getInt('page', 1)),
            itemsPerPage: max(1, min(100, $request->query->getInt('itemsPerPage', 20)))
        );

        return $this->json(
            $this->auditLogService->getPaginatedLogs($filter),
            Response::HTTP_OK
        );
    }

    #[Route('/{uuid}', name: 'app_system_audit_logs_show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/system/audit-logs/{uuid}',
        summary: 'Obtenir le détail d\'une entrée de journal d\'audit par son UUID',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Détail du log d\'audit',
                content: new OA\JsonContent(ref: AuditLogResponse::class)
            ),
            new OA\Response(response: 404, description: 'Entrée introuvable'),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        return $this->json(
            $this->auditLogService->getByUuid($uuid),
            Response::HTTP_OK
        );
    }
}
