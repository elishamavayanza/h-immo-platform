<?php

declare(strict_types=1);

namespace App\Controller\Api\Property;

use App\Dto\Request\PaginationQuery;
use App\Dto\Request\Property\BuildingRequest;
use App\Service\Property\BuildingService;
use App\Trait\FeedbackTrait;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;

/**
 * BuildingController
 *
 * Package : Property Management — Contrôleur API REST
 *
 * Expose les endpoints REST pour la consultation, la création, la modification
 * et la suppression des immeubles/bâtiments (Building).
 */
#[Route('/api/v1/property/buildings', name: 'api_buildings_')]
#[OA\Tag(name: 'Buildings', description: 'Gestion des bâtiments et immeubles érigés sur les parcelles.')]
final class BuildingController extends AbstractController
{
    use FeedbackTrait;

    /**
     * Injecte le service métier lié au domaine des bâtiments.
     * Permet le traitement des opérations REST entrantes.
     */
    public function __construct(
        private readonly BuildingService $buildingService
    ) {
    }

    /**
     * Endpoint API retournant la liste paginée des bâtiments.
     * Mappe la Query String sur le DTO de pagination et renvoie la réponse Feedback.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(
        #[MapQueryString] ?PaginationQuery $query = null
    ): JsonResponse {
        $feedback = $this->buildingService->list($query ?? new PaginationQuery());

        return $this->json($feedback, $feedback->getStatus());
    }

    /**
     * Endpoint API restituant les détails d'un bâtiment par son UUID.
     * Retourne la structure JSON du bâtiment ou un code d'erreur appproprié.
     */
    #[Route('/{uuid}', name: 'show', methods: ['GET'])]
    public function show(string $uuid): JsonResponse
    {
        $feedback = $this->buildingService->getByUuid($uuid);

        return $this->json($feedback, $feedback->getStatus());
    }

    /**
     * Endpoint API créant un nouveau bâtiment sur une parcelle.
     * Désérialise la charge utile JSON et déclenche le service de création.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] BuildingRequest $request
    ): JsonResponse {
        $feedback = $this->buildingService->create($request);

        return $this->json($feedback, $feedback->getStatus());
    }

    /**
     * Endpoint API permettant la mise à jour des données d'un bâtiment.
     * Reçoit le payload de modification et applique les changements.
     */
    #[Route('/{uuid}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(
        string $uuid,
        #[MapRequestPayload] BuildingRequest $request
    ): JsonResponse {
        $feedback = $this->buildingService->update($uuid, $request);

        return $this->json($feedback, $feedback->getStatus());
    }

    /**
     * Endpoint API réalisant la suppression logique d'un bâtiment.
     * Désactive le bâtiment identifié sans impacter physiquement l'historique.
     */
    #[Route('/{uuid}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $uuid): JsonResponse
    {
        $feedback = $this->buildingService->delete($uuid);

        return $this->json($feedback, $feedback->getStatus());
    }
}
