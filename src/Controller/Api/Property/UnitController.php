<?php

declare(strict_types=1);

namespace App\Controller\Api\Property;

use App\Dto\Feedback;
use App\Dto\Request\PaginationQuery;
use App\Dto\Request\Property\UnitRequest;
use App\Dto\Response\HttpErrorResponsePayload;
use App\Dto\Response\Property\UnitResponse;
use App\Service\Property\UnitService;
use App\Trait\FeedbackTrait;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * UnitController
 *
 * Package : Property Management
 *
 * Gestion des unités locatives (appartements, bureaux, commerces).
 * Chaque unité appartient à un Bâtiment, lui-même dans une Parcelle d'une Ville.
 * L'accès est restreint au périmètre Organization → City de l'utilisateur.
 *
 * Endpoints :
 * - POST   /api/v1/units          : créer une unité
 * - GET    /api/v1/units          : lister les unités (pagination, filtres)
 * - GET    /api/v1/units/{uuid}   : détails d'une unité
 * - PUT    /api/v1/units/{uuid}   : modifier une unité
 * - DELETE /api/v1/units/{uuid}   : supprimer (soft delete)
 */
#[Route('/api/v1/units', name: 'api_units_')]
#[OA\Tag(name: 'Units', description: 'Gestion des unités locatives (appartements, bureaux, commerces) dans les bâtiments.')]
final class UnitController extends AbstractController
{
    use FeedbackTrait;

    public function __construct(
        private readonly UnitService $unitService,
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Créer une nouvelle unité locative',
        description: 'Enregistre un nouveau local (appartement, bureau, commerce) rattaché à un bâtiment.'
    )]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: UnitRequest::class)))]
    #[OA\Response(
        response: 201,
        description: 'Unité locative créée avec succès',
        content: new OA\JsonContent(ref: new Model(type: Feedback::class))
    )]
    #[OA\Response(
        response: 422,
        description: 'Erreur de validation',
        content: new OA\JsonContent(ref: new Model(type: Feedback::class))
    )]
    public function create(
        #[MapRequestPayload(validationGroups: ['create'])] UnitRequest $request
    ): JsonResponse {
        $feedback = $this->unitService->create($request);

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        summary: 'Lister les unités locatives avec pagination',
        description: 'Récupère la liste paginée des unités locatives.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des unités locatives récupérée avec succès',
        content: new OA\JsonContent(ref: new Model(type: Feedback::class))
    )]
    public function list(
        #[MapQueryString] ?PaginationQuery $query = null
    ): JsonResponse {
        $feedback = $this->unitService->list($query ?? new PaginationQuery());

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        summary: 'Obtenir les détails d\'une unité locative',
        description: 'Retourne l\'unité locative identifiée par son UUID public.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Détails de l\'unité locative',
        content: new OA\JsonContent(ref: new Model(type: Feedback::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Unité non trouvée',
        content: new OA\JsonContent(ref: new Model(type: HttpErrorResponsePayload::class))
    )]
    public function show(string $uuid): JsonResponse
    {
        $feedback = $this->unitService->getByUuid($uuid);

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}', name: 'update', methods: ['PUT', 'PATCH'])]
    #[OA\Put(
        summary: 'Mettre à jour une unité locative',
        description: 'Modifie les attributs d\'une unité locative existante.'
    )]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: UnitRequest::class)))]
    #[OA\Response(
        response: 200,
        description: 'Unité locative mise à jour',
        content: new OA\JsonContent(ref: new Model(type: Feedback::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Unité non trouvée',
        content: new OA\JsonContent(ref: new Model(type: HttpErrorResponsePayload::class))
    )]
    public function update(
        string $uuid,
        #[MapRequestPayload(validationGroups: ['update'])] UnitRequest $request
    ): JsonResponse {
        $feedback = $this->unitService->update($uuid, $request);

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Supprimer une unité locative (Soft delete)',
        description: 'Marque l\'unité comme supprimée.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Unité locative supprimée',
        content: new OA\JsonContent(ref: new Model(type: Feedback::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Unité non trouvée',
        content: new OA\JsonContent(ref: new Model(type: HttpErrorResponsePayload::class))
    )]
    public function delete(string $uuid): JsonResponse
    {
        $feedback = $this->unitService->delete($uuid);

        return $this->json($feedback, $feedback->getStatus());
    }
}
