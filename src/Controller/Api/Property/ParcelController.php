<?php

declare(strict_types=1);

namespace App\Controller\Api\Property;

use App\Dto\Feedback;
use App\Dto\Request\PaginationQuery;
use App\Dto\Request\Property\ParcelRequest;
use App\Dto\Response\HttpErrorResponsePayload;
use App\Dto\Response\Property\ParcelResponse;
use App\Service\Property\ParcelService;
use App\Trait\FeedbackTrait;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/parcels', name: 'api_parcels_')]
#[OA\Tag(name: 'Parcels')]
final class ParcelController extends AbstractController
{
    use FeedbackTrait;

    public function __construct(
        private readonly ParcelService $parcelService,
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Créer une nouvelle parcelle cadastrale',
        description: 'Enregistre une nouvelle parcelle rattachée à une ville.'
    )]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: ParcelRequest::class, groups: ['create'])))]
    #[OA\Response(
        response: 201,
        description: 'Parcelle créée avec succès',
        content: new OA\JsonContent(ref: new Model(type: Feedback::class))
    )]
    #[OA\Response(
        response: 422,
        description: 'Erreur de validation',
        content: new OA\JsonContent(ref: new Model(type: Feedback::class))
    )]
    public function create(
        #[MapRequestPayload(validationGroups: ['create'])] ParcelRequest $request
    ): JsonResponse {
        $feedback = $this->parcelService->create($request);

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        summary: 'Lister les parcelles avec pagination',
        description: 'Récupère la liste paginée et filtrable des parcelles cadastrales.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des parcelles récupérée avec succès',
        content: new OA\JsonContent(ref: new Model(type: Feedback::class))
    )]
    public function list(
        #[MapQueryString] ?PaginationQuery $query = null
    ): JsonResponse {
        $feedback = $this->parcelService->list($query ?? new PaginationQuery());

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        summary: 'Obtenir les détails d\'une parcelle',
        description: 'Retourne la parcelle identifiée par son UUID public.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Détails de la parcelle',
        content: new OA\JsonContent(ref: new Model(type: Feedback::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Parcelle non trouvée',
        content: new OA\JsonContent(ref: new Model(type: HttpErrorResponsePayload::class))
    )]
    public function show(string $uuid): JsonResponse
    {
        $feedback = $this->parcelService->getByUuid($uuid);

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}', name: 'update', methods: ['PUT', 'PATCH'])]
    #[OA\Put(
        summary: 'Mettre à jour une parcelle',
        description: 'Modifie les attributs d\'une parcelle cadastrale existante.'
    )]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: ParcelRequest::class, groups: ['update'])))]
    #[OA\Response(
        response: 200,
        description: 'Parcelle mise à jour',
        content: new OA\JsonContent(ref: new Model(type: Feedback::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Parcelle non trouvée',
        content: new OA\JsonContent(ref: new Model(type: HttpErrorResponsePayload::class))
    )]
    public function update(
        string $uuid,
        #[MapRequestPayload(validationGroups: ['update'])] ParcelRequest $request
    ): JsonResponse {
        $feedback = $this->parcelService->update($uuid, $request);

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Supprimer une parcelle (Soft delete)',
        description: 'Marque la parcelle comme supprimée sans la retirer physiquement.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Parcelle supprimée',
        content: new OA\JsonContent(ref: new Model(type: Feedback::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Parcelle non trouvée',
        content: new OA\JsonContent(ref: new Model(type: HttpErrorResponsePayload::class))
    )]
    public function delete(string $uuid): JsonResponse
    {
        $feedback = $this->parcelService->delete($uuid);

        return $this->json($feedback, $feedback->getStatus());
    }
}
