<?php

declare(strict_types=1);

namespace App\Controller\Api\Property;

use App\Dto\Feedback;
use App\Dto\Request\Property\PublishListingRequest;
use App\Dto\Request\Property\UnitFilterDto;
use App\Dto\Request\Property\UnitRequest;
use App\Dto\Response\HttpErrorResponsePayload;
use App\Dto\Response\Property\UnitResponse;
use App\Service\Property\PublicShowcaseManagementService;
use App\Service\Property\UnitService;
use App\Trait\FeedbackTrait;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
        private readonly PublicShowcaseManagementService $publicShowcaseManagementService,
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
        summary: 'Lister les unités locatives avec pagination et filtres',
        description: 'Récupère la liste paginée et filtrable des unités locatives.',
        parameters: [
            new OA\Parameter(name: 'organizationId', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'Filtrer par organisation (optionnel)'),
            new OA\Parameter(name: 'buildingUuid', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'Filtrer par bâtiment parent (optionnel)'),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Terme de recherche'),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1), description: 'Numéro de page'),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10, minimum: 1, maximum: 100), description: 'Éléments par page'),
        ],
    )]
    #[OA\Response(
        response: 200,
        description: 'Liste des unités locatives récupérée avec succès',
        content: new OA\JsonContent(ref: new Model(type: Feedback::class))
    )]
    public function list(
        #[MapQueryString] ?UnitFilterDto $filter = null
    ): JsonResponse {
        $feedback = $this->unitService->list($filter);

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

    #[Route('/{uuid}/publish', name: 'publish', methods: ['PATCH'])]
    #[OA\Patch(
        summary: 'Publier ou retirer l\'annonce d\'une unité sur la vitrine publique',
        description: 'Bascule l\'état de publication. Publier une unité occupée est refusé (422).'
    )]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: PublishListingRequest::class)))]
    #[OA\Response(
        response: 200,
        description: 'État de publication mis à jour',
        content: new OA\JsonContent(ref: new Model(type: Feedback::class))
    )]
    #[OA\Response(
        response: 422,
        description: 'Unité occupée (publication refusée) ou identifiant invalide',
        content: new OA\JsonContent(ref: new Model(type: Feedback::class))
    )]
    #[OA\Response(
        response: 403,
        description: 'Accès refusé : droit PUBLISH_LISTING manquant sur la ville de l\'unité'
    )]
    #[OA\Response(
        response: 404,
        description: 'Unité introuvable'
    )]
    public function publish(
        string $uuid,
        #[MapRequestPayload] PublishListingRequest $request
    ): JsonResponse {
        $feedback = $this->publicShowcaseManagementService->publish(
            $this->unitService->findByUuidOrFail($uuid),
            $request
        );

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}/photos', name: 'add_photo', methods: ['POST'])]
    #[OA\Post(
        summary: 'Ajouter une photo à la galerie de l\'unité',
        description: 'Upload d\'une image (JPEG, PNG, WebP, GIF, max 10 Mo). La photo est ajoutée en fin de galerie. Publication non requise.'
    )]
    #[OA\RequestBody(
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(property: 'file', type: 'string', format: 'binary', description: 'Fichier image')
                ],
                required: ['file']
            )
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Photo ajoutée, galerie complète retournée',
        content: new OA\JsonContent(ref: new Model(type: Feedback::class))
    )]
    #[OA\Response(
        response: 413,
        description: 'Fichier trop volumineux (> 10 Mo)'
    )]
    #[OA\Response(
        response: 415,
        description: 'Type de fichier non supporté'
    )]
    #[OA\Response(
        response: 403,
        description: 'Accès refusé : droit PUBLISH_LISTING manquant'
    )]
    #[OA\Response(
        response: 404,
        description: 'Unité introuvable'
    )]
    public function addPhoto(string $uuid, Request $request): JsonResponse
    {
        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile) {
            return $this->json(
                (new Feedback())
                    ->addError('file', 'Aucun fichier fourni.')
                    ->setErrorFlushDescription('Upload requis.')
                    ->setStatus(400)
                    ->autoInitFlush(),
                400
            );
        }

        $feedback = $this->publicShowcaseManagementService->addPhoto(
            $this->unitService->findByUuidOrFail($uuid),
            $file
        );

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}/photos/{photoUuid}', name: 'remove_photo', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Retirer une photo de la galerie d\'une unité',
        description: 'Supprime la photo identifiée par son UUID de la galerie.'
    )]
    #[OA\Response(
        response: 200,
        description: 'Photo retirée, galerie mise à jour retournée',
        content: new OA\JsonContent(ref: new Model(type: Feedback::class))
    )]
    #[OA\Response(
        response: 404,
        description: 'Unité ou photo introuvable'
    )]
    #[OA\Response(
        response: 403,
        description: 'Accès refusé'
    )]
    public function removePhoto(string $uuid, string $photoUuid): JsonResponse
    {
        $feedback = $this->publicShowcaseManagementService->removePhoto($uuid, $photoUuid);

        return $this->json($feedback, $feedback->getStatus());
    }
}
