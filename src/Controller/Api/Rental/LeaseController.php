<?php

declare(strict_types=1);

namespace App\Controller\Api\Rental;

use App\Dto\Feedback;
use App\Dto\Request\Rental\LeaseRequest;
use App\Dto\Request\Rental\LeaseTransitionRequest;
use App\Service\Rental\LeaseService;
use App\Trait\FeedbackTrait;
use Nelmio\ApiDocBundle\Attribute\Model;
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
 * Gestion des contrats de bail (création, lecture, activation, résiliation,
 * annulation). Un bail lie un Locataire à une Unité pour une période, avec
 * un loyer et une devise. L'accès est restreint au périmètre
 * Organization → City de l'utilisateur.
 *
 * Endpoints :
 * - POST   /api/v1/leases                 : créer un bail (état DRAFT)
 * - GET    /api/v1/leases/{uuid}          : détails d'un bail
 * - PUT    /api/v1/leases/{uuid}          : modifier un bail
 * - PATCH  /api/v1/leases/{uuid}/activate  : activer un bail (DRAFT -> ACTIVE)
 * - PATCH  /api/v1/leases/{uuid}/terminate : résilier un bail (ACTIVE -> TERMINATED)
 * - PATCH  /api/v1/leases/{uuid}/cancel    : annuler un bail (DRAFT -> CANCELLED)
 *
 * Un bail ne se supprime pas : il se résilie, ce qui préserve l'historique
 * des échéances qui s'y rattachent. Le statut n'est pas saisissable — il
 * ne change que par les trois transitions ci-dessus, chacune contrôlée.
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
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: LeaseRequest::class))),
        responses: [
            new OA\Response(response: 201, description: 'Bail créé', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 422, description: 'Erreur de validation', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
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
            new OA\Response(response: 200, description: 'Détails du bail', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 404, description: 'Bail non trouvé', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
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
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: LeaseRequest::class))),
        responses: [
            new OA\Response(response: 200, description: 'Bail mis à jour', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 422, description: 'Erreur de validation', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function update(
        string $uuid,
        #[MapRequestPayload] LeaseRequest $request
    ): JsonResponse {
        $feedback = $this->leaseService->updateLease($uuid, $request);

        return $this->json($feedback, $feedback->getStatus());
    }

    /**
     * PATCH /{uuid}/activate — DRAFT -> ACTIVE.
     *
     * Route obligatoire depuis que le statut a quitté `LeaseRequest` :
     * c'est désormais le seul moyen de faire tourner un bail, et il est
     * soumis à `ACTIVATE_LEASE` avec le même verrou d'exclusivité que la
     * création.
     */
    #[Route('/{uuid}/activate', name: 'activate', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/api/v1/leases/{uuid}/activate',
        summary: 'Activer un contrat de bail à l\'état brouillon',
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'UUID du bail')],
        responses: [
            new OA\Response(response: 200, description: 'Bail activé', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 403, description: 'Transition non autorisée', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 404, description: 'Bail non trouvé', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 409, description: 'Unité déjà occupée par un bail actif', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function activate(string $uuid): JsonResponse
    {
        $feedback = $this->leaseService->activateLease($uuid);

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}/terminate', name: 'terminate', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/api/v1/leases/{uuid}/terminate',
        summary: 'Résilier un contrat de bail actif',
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: LeaseTransitionRequest::class))),
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'UUID du bail')],
        responses: [
            new OA\Response(response: 200, description: 'Bail résilié', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 422, description: 'Motif trop long', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 403, description: 'Résiliation non autorisée', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 404, description: 'Bail non trouvé', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 409, description: 'Le bail n\'est pas actif', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function terminate(
        string $uuid,
        #[MapRequestPayload] ?LeaseTransitionRequest $request = null
    ): JsonResponse {
        $feedback = $this->leaseService->terminateLease($uuid, $request?->reason ?? '');

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}/cancel', name: 'cancel', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/api/v1/leases/{uuid}/cancel',
        summary: 'Annuler un contrat de bail à l\'état brouillon',
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: LeaseTransitionRequest::class))),
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'UUID du bail')],
        responses: [
            new OA\Response(response: 200, description: 'Bail annulé', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 422, description: 'Motif trop long', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 403, description: 'Annulation non autorisée', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 404, description: 'Bail non trouvé', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 409, description: 'Le bail n\'est pas à l\'état brouillon', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function cancel(
        string $uuid,
        #[MapRequestPayload] ?LeaseTransitionRequest $request = null
    ): JsonResponse {
        $feedback = $this->leaseService->cancelLease($uuid, $request?->reason ?? '');

        return $this->json($feedback, $feedback->getStatus());
    }
}
