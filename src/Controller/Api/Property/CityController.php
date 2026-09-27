<?php

declare(strict_types=1);

namespace App\Controller\Api\Property;

use App\Dto\Request\PaginationQuery;
use App\Dto\Request\Property\CityRequest;
use App\Service\Property\CityService;
use App\Trait\FeedbackTrait;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;

/**
 * CityController
 *
 * Package : Property Management — Contrôleur API REST
 *
 * Expose les endpoints REST pour l'administration des villes d'exploitation (City).
 * Constitue la racine de la hiérarchie patrimoniale.
 */
#[Route('/api/v1/property/cities', name: 'api_cities_')]
#[OA\Tag(name: 'Cities', description: 'Gestion des villes d\'exploitation du parc immobilier.')]
final class CityController extends AbstractController
{
    use FeedbackTrait;

    /**
     * Injecte le service métier responsable de la gestion des villes.
     * Transmet les demandes HTTP aux méthodes métier appropriées.
     */
    public function __construct(
        private readonly CityService $cityService
    ) {
    }

    /**
     * Endpoint API listant l'ensemble des villes gérées de manière paginée.
     * Récupère la Query String de pagination et renvoie le Feedback au format JSON.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(
        #[MapQueryString] ?PaginationQuery $query = null
    ): JsonResponse {
        $feedback = $this->cityService->list($query ?? new PaginationQuery());

        return $this->json($feedback, $feedback->getStatus());
    }

    /**
     * Endpoint API de consultation d'une ville via son identifiant UUID.
     * Recherche la ville ciblée et retransmet la réponse métier.
     */
    #[Route('/{uuid}', name: 'show', methods: ['GET'])]
    public function show(string $uuid): JsonResponse
    {
        $feedback = $this->cityService->getByUuid($uuid);

        return $this->json($feedback, $feedback->getStatus());
    }

    /**
     * Endpoint API enregistrant une nouvelle ville d'exploitation.
     * Valide la charge utile JSON entrante et crée la ressource.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CityRequest $request
    ): JsonResponse {
        $feedback = $this->cityService->create($request);

        return $this->json($feedback, $feedback->getStatus());
    }

    /**
     * Endpoint API mettant à jour une ville d'exploitation existante.
     * Reçoit les modifications et met à jour l'entité correspondante.
     */
    #[Route('/{uuid}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(
        string $uuid,
        #[MapRequestPayload] CityRequest $request
    ): JsonResponse {
        $feedback = $this->cityService->update($uuid, $request);

        return $this->json($feedback, $feedback->getStatus());
    }

    /**
     * Endpoint API pour la suppression logique d'une ville d'exploitation.
     * Exécute le soft delete de la ville ciblée.
     */
    #[Route('/{uuid}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $uuid): JsonResponse
    {
        $feedback = $this->cityService->delete($uuid);

        return $this->json($feedback, $feedback->getStatus());
    }
}
