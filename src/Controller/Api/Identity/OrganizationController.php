<?php

declare(strict_types=1);

namespace App\Controller\Api\Identity;

use App\Dto\Feedback;
use App\Dto\Request\Identity\OrganizationRequest;
use App\Dto\Request\Identity\OrganizationShowcaseRequest;
use App\Dto\Request\Identity\OrganizationSuspendRequest;
use App\Dto\Request\PaginationQuery;
use App\Dto\Response\Identity\SessionOrganizationMembership;
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
     * Endpoint API résolvant le rôle métier de l'appelant pour l'organisation
     * demandée. C'est la réponse POUR une organisation précise, jamais une
     * liste globale : le client le rappelle à chaque sélection d'organisation.
     */
    #[Route('/{uuid}/membership', name: 'membership', methods: ['GET'])]
    #[OA\Get(
        summary: 'Rôle de l\'appelant pour une organisation',
        description: 'Renvoie le rôle métier de l\'appelant pour l\'organisation demandée (SessionOrganizationMembership). Doit être rappelé à chaque changement d\'organisation active — le rôle est toujours résolu pour une organisation précise, jamais déduit globalement.',
        security: [['bearer' => []]],
    )]
    #[OA\Parameter(
        name: 'uuid',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', format: 'uuid'),
        example: '7b2e0d1a-4c3f-4a2b-9e1d-5c6f7a8b9c0d'
    )]
    #[OA\Response(
        response: 200,
        description: 'Rôle résolu pour l\'organisation demandée',
        content: new OA\JsonContent(ref: new Model(type: SessionOrganizationMembership::class))
    )]
    #[OA\Response(
        response: 403,
        description: 'Non membre de l\'organisation ou aucun rôle métier pour elle'
    )]
    #[OA\Response(
        response: 404,
        description: 'Organisation introuvable'
    )]
    public function membership(string $uuid): JsonResponse
    {
        $feedback = $this->organizationService->getMembership($uuid);

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

    /**
     * Suspend une organisation avec un motif obligatoire, notifie ses membres
     * par email et archive le motif dans l'audit. Un tenant suspendu perd
     * l'accès de tous ses membres tant qu'il n'est pas réactivé.
     */
    #[Route('/{uuid}/suspend', name: 'suspend', methods: ['POST'])]
    #[OA\Post(
        summary: 'Suspendre une organisation',
        description: 'Suspend un tenant avec un motif obligatoire. Le motif est archivé dans l\'audit et les membres de l\'organisation sont notifiés par email. Un tenant suspendu perd l\'accès de tous ses membres.',
        security: [['bearer' => []]],
    )]
    #[OA\Parameter(
        name: 'uuid',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', format: 'uuid'),
        example: '7b2e0d1a-4c3f-4a2b-9e1d-5c6f7a8b9c0d'
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(ref: new Model(type: OrganizationSuspendRequest::class))
    )]
    #[OA\Response(
        response: 200,
        description: 'Organisation suspendue, membres notifiés',
        content: new OA\JsonContent(ref: new Model(type: Feedback::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Organisation introuvable'
    )]
    #[OA\Response(
        response: 422,
        description: 'Motif manquant ou organisation non active'
    )]
    public function suspend(
        string $uuid,
        #[MapRequestPayload] OrganizationSuspendRequest $request
    ): JsonResponse {
        $feedback = $this->organizationService->suspend($uuid, $request);

        return $this->json($feedback, $feedback->getStatus());
    }

    /**
     * Réactive une organisation précédemment suspendue : le statut repasse à
     * `active` et les accès des membres sont rétablis.
     */
    #[Route('/{uuid}/reactivate', name: 'reactivate', methods: ['POST'])]
    #[OA\Post(
        summary: 'Réactiver une organisation',
        description: 'Réactive une organisation suspendue (statut repasse à `active`), rétablissant les accès de ses membres.',
        security: [['bearer' => []]],
    )]
    #[OA\Parameter(
        name: 'uuid',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'string', format: 'uuid'),
        example: '7b2e0d1a-4c3f-4a2b-9e1d-5c6f7a8b9c0d'
    )]
    #[OA\Response(
        response: 200,
        description: 'Organisation réactivée',
        content: new OA\JsonContent(ref: new Model(type: Feedback::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Organisation introuvable'
    )]
    #[OA\Response(
        response: 422,
        description: 'Organisation non suspendue'
    )]
    public function reactivate(string $uuid): JsonResponse
    {
        $feedback = $this->organizationService->reactivate($uuid);

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
