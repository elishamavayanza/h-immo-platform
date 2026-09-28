<?php

declare(strict_types=1);

namespace App\Controller\Api\Staff;

use App\Dto\Feedback;
use App\Dto\Request\Staff\WorkerRequest;
use App\Service\Staff\WorkerService;
use App\Trait\FeedbackTrait;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * WorkerController
 *
 * Package : Staff Management
 *
 * Gestion des travailleurs (création, lecture, mise à jour).
 * Un travailleur appartient à une Organization. L'accès est restreint
 * au périmètre Organization de l'utilisateur.
 *
 * L'ADMIN_VILLE ne gère pas directement les Workers (uniquement via
 * WorkerAssignment) — cela est appliqué par SecurityService::checkWorkerAccess.
 *
 * Endpoints :
 * - POST   /api/v1/workers          : créer un travailleur
 * - GET    /api/v1/workers          : lister les travailleurs
 * - GET    /api/v1/workers/{uuid}   : détails d'un travailleur
 * - PUT    /api/v1/workers/{uuid}   : modifier un travailleur
 */
#[Route('/api/v1/workers', name: 'api_workers_')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Workers', description: 'Gestion des travailleurs (fiche identité, organisation).')]
final class WorkerController extends AbstractController
{
    use FeedbackTrait;

    public function __construct(
        private readonly WorkerService $workerService
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/workers',
        summary: 'Créer un nouveau travailleur',
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: WorkerRequest::class))),
        responses: [
            new OA\Response(response: 201, description: 'Travailleur créé', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 422, description: 'Erreur de validation', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function create(
        #[MapRequestPayload] WorkerRequest $request
    ): JsonResponse {
        $feedback = $this->workerService->createWorker($request, $this->getUser());

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/workers',
        summary: 'Lister les travailleurs de l\'organisation',
        parameters: [
            new OA\Parameter(name: 'organizationId', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'Filtrer par organisation (optionnel)'),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1), description: 'Numéro de page'),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20, minimum: 1, maximum: 100), description: 'Éléments par page'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function list(
        #[MapRequestPayload] \App\Dto\Request\Staff\WorkerFilterDto $filter
    ): JsonResponse {
        $feedback = $this->workerService->listWorkers(
            organizationId: $filter->organizationId !== null ? \Symfony\Component\Uid\Uuid::fromString($filter->organizationId) : null,
            page: $filter->page,
            limit: $filter->limit
        );

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/workers/{uuid}',
        summary: 'Obtenir les détails d\'un travailleur',
        responses: [
            new OA\Response(response: 200, description: 'Détails du travailleur', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 404, description: 'Travailleur introuvable', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        $feedback = $this->workerService->getWorkerByUuid($uuid);

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}', name: 'update', methods: ['PUT', 'PATCH'])]
    #[OA\Put(
        path: '/api/v1/workers/{uuid}',
        summary: 'Mettre à jour un travailleur',
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: WorkerRequest::class))),
        responses: [
            new OA\Response(response: 200, description: 'Travailleur mis à jour', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 422, description: 'Erreur de validation', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function update(
        string $uuid,
        #[MapRequestPayload] WorkerRequest $request
    ): JsonResponse {
        $feedback = $this->workerService->updateWorker($uuid, $request, $this->getUser());

        return $this->json($feedback, $feedback->getStatus());
    }
}