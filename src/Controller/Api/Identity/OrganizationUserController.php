<?php

declare(strict_types=1);

namespace App\Controller\Api\Identity;

use App\Dto\Request\Identity\OrganizationUserRequest;
use App\Dto\Request\PaginationQuery;
use App\Service\Identity\OrganizationUserService;
use App\Trait\FeedbackTrait;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * OrganizationUserController
 *
 * Package : Identity & Access — Contrôleur API REST
 *
 * Gère les points d'accès API pour le rattachement des utilisateurs aux organisations,
 * l'attribution des rôles applicatifs et la révocation des accès.
 */
#[Route('/api/v1/identity/organization-users', name: 'api_organization_users_')]
#[OA\Tag(name: 'Organization Users', description: 'Gestion des utilisateurs et rôles par organisation.')]
final class OrganizationUserController extends AbstractController
{
    use FeedbackTrait;

    /**
     * Injecte le service d'administration des utilisateurs au sein des organisations.
     * Permet le pilotage des permissions et des droits multi-tenant.
     */
    public function __construct(
        private readonly OrganizationUserService $orgUserService
    ) {
    }

    /**
     * Endpoint API répertoriant tous les utilisateurs rattachés à une organisation spécifique.
     * Filtre les affectations par l'UUID de l'organisation transmise.
     */
    #[Route('/organization/{orgUuid}', name: 'list_by_org', methods: ['GET'])]
    public function listByOrganization(
        string $orgUuid,
        #[MapQueryString] ?PaginationQuery $query = null
    ): JsonResponse {
        $feedback = $this->orgUserService->listByOrganization($orgUuid, $query ?? new PaginationQuery());

        return $this->json($feedback, $feedback->getStatus());
    }

    /**
     * Endpoint API associant un utilisateur à une organisation avec un rôle précis.
     * Enregistre le couple (User, Organization) en garantissant l'unicité de l'accès.
     */
    #[Route('', name: 'assign', methods: ['POST'])]
    public function assign(
        #[MapRequestPayload] OrganizationUserRequest $request
    ): JsonResponse {
        $feedback = $this->orgUserService->assignUser($request);

        return $this->json($feedback, $feedback->getStatus());
    }

    /**
     * Endpoint API modifiant le rôle d'un utilisateur dans une organisation.
     * Met à jour le champ OrganizationRole pour l'affectation donnée.
     */
    #[Route('/{uuid}', name: 'update_role', methods: ['PUT', 'PATCH'])]
    public function updateRole(
        string $uuid,
        #[MapRequestPayload] OrganizationUserRequest $request
    ): JsonResponse {
        $feedback = $this->orgUserService->updateRole($uuid, $request);

        return $this->json($feedback, $feedback->getStatus());
    }

    /**
     * Endpoint API révoquant l'accès d'un utilisateur à une organisation.
     * Supprime l'enregistrement de liaison et retire les droits associés.
     */
    #[Route('/{uuid}', name: 'revoke', methods: ['DELETE'])]
    public function revoke(string $uuid): JsonResponse
    {
        $feedback = $this->orgUserService->revokeUser($uuid);

        return $this->json($feedback, $feedback->getStatus());
    }
}
