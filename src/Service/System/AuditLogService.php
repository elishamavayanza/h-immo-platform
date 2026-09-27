<?php

declare(strict_types=1);

namespace App\Service\System;

use App\Dto\Request\System\AuditLogFilterDto;
use App\Dto\Response\System\AuditLogResponse;
use App\Entity\Identity\Organization;
use App\Entity\Identity\User;
use App\Mapper\System\AuditLogMapper;
use App\Repository\Identity\OrganizationRepository;
use App\Repository\System\AuditLogRepository;
use App\Security\SecurityAction;
use App\Security\SecurityServiceInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

final class AuditLogService
{
    public function __construct(
        private readonly AuditLogRepository $auditLogRepository,
        private readonly OrganizationRepository $organizationRepository,
        private readonly AuditLogMapper $mapper,
        private readonly SecurityServiceInterface $security,
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
     * Le journal d'audit contient les valeurs avant/après de chaque
     * modification sur toute la plateforme : il est borné à l'Organization
     * demandée, et cette Organization est vérifiée avant la requête. Sans
     * ce double contrôle, n'importe quel utilisateur authentifié pourrait
     * lire l'historique complet des autres tenants.
     *
     * @return array{items: list<AuditLogResponse>, total: int, page: int, pages: int}
     */
    public function getPaginatedLogs(AuditLogFilterDto $filter): array
    {
        $organization = null;
        $organizations = null;

        if ($filter->organizationUuid !== null) {
            $organization = $this->resolveOrganization($filter->organizationUuid);

            $this->security->checkOrganizationAuditLogAccess($organization, SecurityAction::VIEW_AUDIT_LOG);
        } elseif (!$this->security->isSuperAdmin()) {
            // Sans Organization demandée, on ne renvoie que les
            // événements des Organizations dont l'appelant est membre.
            // La liste doit être transmise au repository : ne vérifier
            // l'accès sans pas filtrer la requête renverrait les
            // journaux de tous les tenants.
            $organizations = [];

            foreach ($this->security->getCurrentUserOrganizations() as $currentOrganization) {
                $this->security->checkOrganizationAuditLogAccess(
                    $currentOrganization,
                    SecurityAction::VIEW_AUDIT_LOG
                );

                $organizations[] = $currentOrganization;
            }
        }

        $result = $this->auditLogRepository->findByFilter(
            $organization,
            $filter->action,
            $filter->entityType,
            $filter->from,
            $filter->to,
            $filter->page,
            $filter->itemsPerPage,
            $organizations
        );

        $pagesCount = (int) ceil($result['total'] / max(1, $filter->itemsPerPage));

        return [
            'items' => $this->mapper->toResponseCollection($result['items']),
            'total' => $result['total'],
            'page' => $filter->page,
            'pages' => max(1, $pagesCount),
        ];
    }

    /**
     * Récupère un log d'audit par son UUID.
     */
    public function getByUuid(string $uuid): AuditLogResponse
    {
        $auditLog = $this->auditLogRepository->findOneBy(['uuid' => $uuid]);

        if (!$auditLog) {
            throw new NotFoundHttpException(sprintf('Audit log with UUID "%s" not found.', $uuid));
        }

        $this->security->checkAuditLogAccess($auditLog, SecurityAction::VIEW_AUDIT_LOG);

        return $this->mapper->toResponse($auditLog);
    }

    private function resolveOrganization(string $uuid): Organization
    {
        $organization = $this->organizationRepository->findOneByUuid(Uuid::fromString($uuid));

        if (!$organization) {
            throw new NotFoundHttpException(sprintf('Organization with UUID "%s" not found.', $uuid));
        }

        return $organization;
    }
}
