<?php

declare(strict_types=1);

namespace App\Controller\Api\Identity;

use App\Dto\Feedback;
use App\Dto\Request\Identity\OrganizationRequest;
use App\Dto\Request\Identity\OrganizationShowcaseRequest;
use App\Dto\Request\PaginationQuery;
use App\Service\Identity\OrganizationService;
use App\Trait\FeedbackTrait;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * OrganizationController
 *
 * Package : Identity & Access — Contrôleur API REST
 *
 * Expose les points d'entrée d'API pour la gestion CRUD des organisations (Multi-tenant).
 * Retourne exclusivement des objets JSON structurés en enveloppes Feedback.
 */
#[Route('/api/v1/identity/organizations', name: 'api_organizations_')]
#[OA\Tag(name: 'Organizations', description: 'Gestion des entreprises clientes (tenants).')]
final class OrganizationController extends AbstractController
{
    use FeedbackTrait;

    /**
     * Injecte le service métier dédié à la gestion des organisations.
     * Délègue les règles d'entreprise au domaine d'application.
     */
    public function __construct(
        private readonly OrganizationService $organizationService
    ) {
    }

    /**
     * Endpoint API pour lister les organisations de la plateforme avec pagination.
     * Accepte des filtres HTTP en Query String et retourne la liste dans un Feedback.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(
        #[MapQueryString] ?PaginationQuery $query = null
    ): JsonResponse {
        $feedback = $this->organizationService->list($query ?? new PaginationQuery());

        return $this->json($feedback, $feedback->getStatus());
    }

    /**
     * Endpoint API pour afficher les détails d'une organisation par son UUID.
     * Recherche l'entité et retourne son DTO structuré sous forme de réponse JSON.
     */
    #[Route('/{uuid}', name: 'show', methods: ['GET'])]
    public function show(string $uuid): JsonResponse
    {
        $feedback = $this->organizationService->getByUuid($uuid);

        return $this->json($feedback, $feedback->getStatus());
    }

    /**
     * Endpoint API créant une nouvelle organisation client dans le système.
     * Désérialise et valide le payload entrant avant de traiter la création.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] OrganizationRequest $request
    ): JsonResponse {
        $feedback = $this->organizationService->create($request);

        return $this->json($feedback, $feedback->getStatus());
    }

    /**
     * Endpoint API permettant la mise à jour des paramètres d'une organisation.
     * Traite les modifications partielles ou complètes du payload fourni.
     */
    #[Route('/{uuid}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(
        string $uuid,
        #[MapRequestPayload] OrganizationRequest $request
    ): JsonResponse {
        $feedback = $this->organizationService->update($uuid, $request);

        return $this->json($feedback, $feedback->getStatus());
    }

    /**
     * Endpoint API supprimant virtuellement (Soft Delete) une organisation.
     * Marque l'entité comme archivée et désactive l'accès multi-tenant.
     */
    #[Route('/{uuid}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $uuid): JsonResponse
    {
        $feedback = $this->organizationService->delete($uuid);

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}/showcase', name: 'update_showcase', methods: ['PATCH'])]
    #[OA\Patch(
        summary: 'Mettre à jour la vitrine publique de l\'entreprise',
        description: 'Modifie le slug, la description publique et la visibilité de la vitrine. Réservé au PATRON.'
    )]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: OrganizationShowcaseRequest::class)))]
    #[OA\Response(
        response: 200,
        description: 'Vitrine mise à jour',
        content: new OA\JsonContent(ref: new Model(type: Feedback::class))
    )]
    #[OA\Response(
        response: 409,
        description: 'Slug déjà utilisé par une autre entreprise'
    )]
    #[OA\Response(
        response: 422,
        description: 'Données invalides (slug mal formaté)'
    )]
    #[OA\Response(
        response: 403,
        description: 'Accès réservé au PATRON'
    )]
    #[OA\Response(
        response: 404,
        description: 'Organisation introuvable'
    )]
    public function updateShowcase(
        string $uuid,
        #[MapRequestPayload] OrganizationShowcaseRequest $request
    ): JsonResponse {
        $feedback = $this->organizationService->updateShowcase($uuid, $request);

        return $this->json($feedback, $feedback->getStatus());
    }
}
