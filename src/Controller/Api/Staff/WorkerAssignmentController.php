<?php

declare(strict_types=1);

namespace App\Controller\Api\Staff;

use App\Dto\Feedback;
use App\Dto\Request\Staff\WorkerAssignmentFilterDto;
use App\Dto\Request\Staff\WorkerAssignmentRequest;
use App\Service\Staff\WorkerAssignmentService;
use App\Trait\FeedbackTrait;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * WorkerAssignmentController
 *
 * Package : Staff Management
 *
 * Gestion des affectations de travailleurs (création, lecture, mise à jour, fin).
 * Une affectation lie un Worker à une cible (parcel/building/unit) dans une ville.
 * L'accès est restreint au périmètre Organization → City de l'utilisateur.
 *
 * Endpoints :
 * - POST   /api/v1/worker-assignments                 : créer une affectation
 * - GET    /api/v1/worker-assignments                 : lister les affectations
 * - GET    /api/v1/worker-assignments/{uuid}          : détails d'une affectation
 * - PUT    /api/v1/worker-assignments/{uuid}          : modifier une affectation
 * - POST   /api/v1/worker-assignments/{uuid}/end      : terminer une affectation
 */
#[Route('/api/v1/worker-assignments', name: 'api_worker_assignments_')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Worker Assignments', description: 'Gestion des affectations de travailleurs (ville, cible, rôle, salaire, dates).')]
final class WorkerAssignmentController extends AbstractController
{
    use FeedbackTrait;

    public function __construct(
        private readonly WorkerAssignmentService $assignmentService
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/worker-assignments',
        summary: 'Créer une nouvelle affectation',
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: WorkerAssignmentRequest::class))),
        responses: [
            new OA\Response(response: 201, description: 'Affectation créée', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 422, description: 'Erreur de validation', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function create(
        #[MapRequestPayload] WorkerAssignmentRequest $request
    ): JsonResponse {
        $feedback = $this->assignmentService->createAssignment($request, $this->getUser());

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/worker-assignments',
        summary: 'Lister les affectations avec pagination',
        parameters: [
            new OA\Parameter(name: 'cityIds', in: 'query', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', format: 'uuid')), description: 'Filtrer par villes (UUIDs)'),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1), description: 'Numéro de page'),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20, minimum: 1, maximum: 100), description: 'Éléments par page'),
            new OA\Parameter(name: 'sortBy', in: 'query', schema: new OA\Schema(type: 'string', default: 'startDate'), description: 'Champ de tri'),
            new OA\Parameter(name: 'sortOrder', in: 'query', schema: new OA\Schema(type: 'string', enum: ['ASC', 'DESC'], default: 'DESC'), description: 'Ordre de tri'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function list(
        #[MapQueryString] WorkerAssignmentFilterDto $filter
    ): JsonResponse {
        $feedback = $this->assignmentService->listAssignments(
            cityIds: $filter->cityIds,
            page: $filter->page,
            limit: $filter->limit,
            sortBy: $filter->sortBy,
            sortOrder: $filter->sortOrder
        );

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/worker-assignments/{uuid}',
        summary: 'Obtenir les détails d\'une affectation',
        responses: [
            new OA\Response(response: 200, description: 'Détails de l\'affectation', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 404, description: 'Affectation introuvable', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        $feedback = $this->assignmentService->getAssignmentByUuid($uuid);

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}', name: 'update', methods: ['PUT', 'PATCH'])]
    #[OA\Put(
        path: '/api/v1/worker-assignments/{uuid}',
        summary: 'Mettre à jour une affectation',
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: WorkerAssignmentRequest::class))),
        responses: [
            new OA\Response(response: 200, description: 'Affectation mise à jour', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 422, description: 'Erreur de validation', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function update(
        string $uuid,
        #[MapRequestPayload] WorkerAssignmentRequest $request
    ): JsonResponse {
        $feedback = $this->assignmentService->updateAssignment($uuid, $request, $this->getUser());

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}/end', name: 'end', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/worker-assignments/{uuid}/end',
        summary: 'Terminer une affectation (poser endDate)',
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: \App\Dto\Request\Staff\WorkerAssignmentEndRequest::class))),
        responses: [
            new OA\Response(response: 200, description: 'Affectation terminée', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 422, description: 'Date invalide', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function end(
        string $uuid,
        #[MapRequestPayload] \App\Dto\Request\Staff\WorkerAssignmentEndRequest $request
    ): JsonResponse {
        $feedback = $this->assignmentService->endAssignment($uuid, $request->endDate, $this->getUser());

        return $this->json($feedback, $feedback->getStatus());
    }
}