<?php

declare(strict_types=1);

namespace App\Controller\Api\Rental;

use App\Dto\Feedback;
use App\Dto\Request\Rental\RentOverdueFilterDto;
use App\Dto\Request\Rental\RentRequest;
use App\Entity\Identity\User;
use App\Service\Rental\RentService;
use App\Trait\FeedbackTrait;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * RentController
 *
 * Package : Rental Management
 *
 * Gestion des échéances de loyer (génération, lecture, mise à jour, marquage impayé).
 * Une échéance est générée à partir d'un Bail pour une période donnée (mois/année).
 * L'accès est restreint au périmètre Organization → City de l'utilisateur.
 *
 * Endpoints :
 * - POST   /api/v1/rents          : générer une échéance
 * - GET    /api/v1/rents          : lister les échéances (pagination)
 * - GET    /api/v1/rents/{uuid}   : détails d'une échéance
 * - PUT    /api/v1/rents/{uuid}   : modifier une échéance
 * - PATCH  /api/v1/rents/{uuid}/overdue : marquer comme impayée
 * - DELETE /api/v1/rents/{uuid}   : supprimer (soft delete)
 */
#[Route('/api/v1/rents', name: 'api_rents_')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Rents', description: 'Gestion des échéances de loyer (période, montant, statut, bail).')]
final class RentController extends AbstractController
{
    use FeedbackTrait;

    public function __construct(
        private readonly RentService $rentService
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/rents',
        summary: 'Générer une nouvelle échéance de loyer',
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: RentRequest::class))),
        responses: [
            new OA\Response(response: 201, description: 'Échéance créée', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 422, description: 'Erreur de validation', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function create(
        #[MapRequestPayload] RentRequest $request
    ): JsonResponse {
        $feedback = $this->rentService->createRent($request, $this->getUser());

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/rents/{uuid}',
        summary: 'Obtenir les détails d\'une échéance de loyer',
        responses: [
            new OA\Response(response: 200, description: 'Détails de l\'échéance', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 404, description: 'Échéance introuvable', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        $feedback = $this->rentService->getRentByUuid($uuid);

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}', name: 'update', methods: ['PUT', 'PATCH'])]
    #[OA\Put(
        path: '/api/v1/rents/{uuid}',
        summary: 'Mettre à jour une échéance de loyer',
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: RentRequest::class))),
        responses: [
            new OA\Response(response: 200, description: 'Échéance mise à jour', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 422, description: 'Erreur de validation', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function update(
        string $uuid,
        #[MapRequestPayload] RentRequest $request
    ): JsonResponse {
        $feedback = $this->rentService->updateRent($uuid, $request, $this->getUser());

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/overdue', name: 'overdue_list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/rents/overdue',
        summary: 'Lister les échéances en retard avec pagination',
        parameters: [
            new OA\Parameter(name: 'organizationId', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'Filtrer par organisation (optionnel)'),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1), description: 'Numéro de page'),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20, minimum: 1, maximum: 100), description: 'Éléments par page'),
            new OA\Parameter(name: 'sortBy', in: 'query', schema: new OA\Schema(type: 'string', default: 'dueDate'), description: 'Champ de tri'),
            new OA\Parameter(name: 'sortOrder', in: 'query', schema: new OA\Schema(type: 'string', enum: ['ASC', 'DESC'], default: 'ASC'), description: 'Ordre de tri'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function listOverdue(
        #[MapRequestPayload] RentOverdueFilterDto $filter
    ): JsonResponse {
        $feedback = $this->rentService->listOverdueRents($filter);

        return $this->json($feedback, $feedback->getStatus());
    }
}
