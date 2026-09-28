<?php

declare(strict_types=1);

namespace App\Controller\Api\Rental;

use App\Dto\Feedback;
use App\Dto\Request\Rental\TenantRequest;
use App\Service\Rental\TenantService;
use App\Trait\FeedbackTrait;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * TenantController
 *
 * Package : Rental Management
 *
 * Gestion des locataires (création, lecture, mise à jour, archivage).
 * Un locataire peut être une personne physique ou morale, rattaché à une Organization.
 * L'accès est restreint au périmètre Organization → City de l'utilisateur.
 *
 * Endpoints :
 * - POST   /api/v1/tenants          : créer un locataire
 * - GET    /api/v1/tenants          : lister les locataires (pagination, recherche)
 * - GET    /api/v1/tenants/{uuid}   : détails d'un locataire
 * - PUT    /api/v1/tenants/{uuid}   : modifier un locataire
 * - PATCH  /api/v1/tenants/{uuid}/archive : archiver un locataire
 * - DELETE /api/v1/tenants/{uuid}   : supprimer (soft delete)
 */
#[Route('/api/v1/tenants', name: 'api_tenants_')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Tenants', description: 'Gestion des locataires (personnes physiques ou morales) rattachés à une organisation.')]
final class TenantController extends AbstractController
{
    use FeedbackTrait;

    public function __construct(
        private readonly TenantService $tenantService
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/tenants',
        summary: 'Créer un nouveau locataire',
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: TenantRequest::class))),
        responses: [
            new OA\Response(response: 201, description: 'Locataire créé', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 422, description: 'Erreur de validation', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function create(
        #[MapRequestPayload] TenantRequest $request
    ): JsonResponse {
        $feedback = $this->tenantService->createTenant($request);

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/tenants/{uuid}',
        summary: 'Obtenir les détails d\'un locataire',
        responses: [
            new OA\Response(response: 200, description: 'Détails du locataire', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 404, description: 'Locataire non trouvé', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        $feedback = $this->tenantService->getTenantByUuid($uuid);

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}', name: 'update', methods: ['PUT', 'PATCH'])]
    #[OA\Put(
        path: '/api/v1/tenants/{uuid}',
        summary: 'Mettre à jour la fiche d\'un locataire',
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: TenantRequest::class))),
        responses: [
            new OA\Response(response: 200, description: 'Locataire mis à jour', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 422, description: 'Erreur de validation', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function update(
        string $uuid,
        #[MapRequestPayload] TenantRequest $request
    ): JsonResponse {
        $feedback = $this->tenantService->updateTenant($uuid, $request);

        return $this->json($feedback, $feedback->getStatus());
    }
}
