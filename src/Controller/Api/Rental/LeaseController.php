<?php

declare(strict_types=1);

namespace App\Controller\Api\Rental;

use App\Dto\Feedback;
use App\Dto\Request\Rental\LeaseRequest;
use App\Service\Rental\LeaseService;
use App\Trait\FeedbackTrait;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * LeaseController
 *
 * Package : Rental Management
 *
 * Gestion des contrats de bail (création, lecture, activation, résiliation).
 * Un bail lie un Locataire à une Unité pour une période, avec un loyer et une devise.
 * L'accès est restreint au périmètre Organization → City de l'utilisateur.
 *
 * Endpoints :
 * - POST   /api/v1/leases          : créer un bail
 * - GET    /api/v1/leases          : lister les baux (pagination)
 * - GET    /api/v1/leases/{uuid}   : détails d'un bail
 * - PUT    /api/v1/leases/{uuid}   : modifier un bail
 * - PATCH  /api/v1/leases/{uuid}/activate   : activer un bail
 * - PATCH  /api/v1/leases/{uuid}/terminate  : résilier un bail
 * - PATCH  /api/v1/leases/{uuid}/cancel     : annuler un bail
 * - DELETE /api/v1/leases/{uuid}   : supprimer (soft delete)
 */
#[Route('/api/v1/leases', name: 'api_leases_')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Leases', description: 'Gestion des contrats de bail (locataire, unité, loyer, période).')]
final class LeaseController extends AbstractController
{
    use FeedbackTrait;

    public function __construct(
        private readonly LeaseService $leaseService
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/leases',
        summary: 'Créer un nouveau contrat de bail',
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: LeaseRequest::class)),
        responses: [
            new OA\Response(response: 201, description: 'Bail créé', content: new OA\JsonContent(ref: Feedback::class)),
            new OA\Response(response: 422, description: 'Erreur de validation', content: new OA\JsonContent(ref: Feedback::class)),
        ]
    )]
    public function create(
        #[MapRequestPayload] LeaseRequest $request
    ): JsonResponse {
        $feedback = $this->leaseService->createLease($request);

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/leases/{uuid}',
        summary: 'Obtenir les détails d\'un contrat de bail',
        responses: [
            new OA\Response(response: 200, description: 'Détails du bail', content: new OA\JsonContent(ref: Feedback::class)),
            new OA\Response(response: 404, description: 'Bail non trouvé', content: new OA\JsonContent(ref: Feedback::class)),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        $feedback = $this->leaseService->getLeaseByUuid($uuid);

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}', name: 'update', methods: ['PUT', 'PATCH'])]
    #[OA\Put(
        path: '/api/v1/leases/{uuid}',
        summary: 'Mettre à jour un contrat de bail',
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: LeaseRequest::class)),
        responses: [
            new OA\Response(response: 200, description: 'Bail mis à jour', content: new OA\JsonContent(ref: Feedback::class)),
            new OA\Response(response: 422, description: 'Erreur de validation', content: new OA\JsonContent(ref: Feedback::class)),
        ]
    )]
    public function update(
        string $uuid,
        #[MapRequestPayload] LeaseRequest $request
    ): JsonResponse {
        $feedback = $this->leaseService->updateLease($uuid, $request);

        return $this->json($feedback, $feedback->getStatus());
    }
}
