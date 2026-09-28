<?php

declare(strict_types=1);

namespace App\Controller\Api\System;

use App\Dto\Feedback;
use App\Service\System\FileUploadService;
use App\Service\System\MediaService;
use App\Trait\FeedbackTrait;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * MediaController
 *
 * Package : System & Audit
 *
 * Gestion des médias (photos de profil, logos, photos de parcelle).
 *
 * Les fichiers sont stockés dans public/uploads/{type}/ et exposés via
 * /uploads/{type}/{file}. Ce chemin est servi par le serveur web sans
 * authentification : il ne contient donc QUE des images, jamais de pièces
 * justificatives. Voir `FileUploadService` pour ce choix.
 *
 * L'entité ciblée est TOUJOURS résolue par son UUID puis soumise à
 * `MediaService`, qui applique le contrôle d'accès et rattache le fichier
 * en base. Aucun point d'entrée ne se contente de ROLE_USER : l'UUID
 * présent dans l'URL n'est pas une autorisation, et un compte authentifié
 * de n'importe quelle société pouvait déposer ou supprimer un fichier pour
 * une entité qui n'est pas la sienne.
 */
#[Route('/api/v1/media', name: 'api_media_')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Media', description: 'Photos de profil, logos d\'organisation et photos de parcelle.')]
final class MediaController extends AbstractController
{
    use FeedbackTrait;

    public function __construct(
        private readonly MediaService $mediaService,
        private readonly FileUploadService $fileUploadService,
    ) {
    }

    // ==================== USER PROFILE PHOTO ====================

    #[Route('/users/{uuid}/photo', name: 'user_photo_upload', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/media/users/{uuid}/photo',
        summary: 'Uploader la photo de profil d\'un utilisateur',
        description: 'Remplace la photo existante et l\'enregistre sur l\'utilisateur. Types acceptés : JPEG, PNG, WebP, GIF. Max 10 Mo.',
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            description: 'Fichier image (multipart/form-data)',
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary', description: 'Fichier image (JPEG, PNG, WebP, GIF, max 10 Mo)')
                    ],
                    required: ['file']
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Photo uploadée', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 422, description: 'Fichier invalide', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 404, description: 'Utilisateur introuvable', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function uploadUserPhoto(string $uuid, Request $request): JsonResponse
    {
        $file = $request->files->get('file');

        if (!$file) {
            return $this->json(
                (new Feedback())->addError('file', 'Aucun fichier fourni.')->setErrorFlushDescription('Fichier requis.')->setStatus(422)->autoInitFlush(),
                422
            );
        }

        $user = $this->mediaService->resolveUser($uuid);

        if ($user === null) {
            return $this->json(
                (new Feedback())->setErrorFlushDescription('Utilisateur introuvable.')->setStatus(404)->autoInitFlush(),
                404
            );
        }

        try {
            $relativePath = $this->mediaService->replaceUserPhoto($user, $file);
        } catch (\InvalidArgumentException $e) {
            return $this->json(
                (new Feedback())->addError('file', $e->getMessage())->setErrorFlushDescription($e->getMessage())->setStatus(422)->autoInitFlush(),
                422
            );
        }

        return $this->json(
            (new Feedback())->setData([
                'path' => $relativePath,
                'url' => $this->fileUploadService->getPublicUrl($relativePath),
            ])->setFlushDescription('Photo de profil mise à jour.')->setStatus(200)->autoInitFlush(),
            200
        );
    }

    #[Route('/users/{uuid}/photo', name: 'user_photo_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/v1/media/users/{uuid}/photo',
        summary: 'Supprimer la photo de profil d\'un utilisateur',
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Photo supprimée', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 404, description: 'Utilisateur introuvable', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function deleteUserPhoto(string $uuid): JsonResponse
    {
        $user = $this->mediaService->resolveUser($uuid);

        if ($user === null) {
            return $this->json(
                (new Feedback())->setErrorFlushDescription('Utilisateur introuvable.')->setStatus(404)->autoInitFlush(),
                404
            );
        }

        $removed = $this->mediaService->removeUserPhoto($user);

        $feedback = new Feedback();

        if (!$removed) {
            // 404 et non 200 : l'endpoint doit signaler qu'il n'y avait rien
            // à supprimer, sinon un client ne peut pas distinguer une
            // suppression réussie d'un appel sans effet.
            return $this->json(
                $feedback->setErrorFlushDescription('Aucune photo de profil à supprimer.')->setStatus(404)->autoInitFlush(),
                404
            );
        }

        return $this->json(
            $feedback->setFlushDescription('Photo supprimée.')->setStatus(200)->autoInitFlush(),
            200
        );
    }

    // ==================== ORGANIZATION LOGO ====================

    #[Route('/organizations/{uuid}/logo', name: 'org_logo_upload', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/media/organizations/{uuid}/logo',
        summary: 'Uploader le logo d\'une organisation',
        description: 'Remplace le logo existant et l\'enregistre sur l\'organisation. Réservé au Patron. Types acceptés : JPEG, PNG, WebP, GIF. Max 10 Mo.',
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            description: 'Fichier image (multipart/form-data)',
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary', description: 'Fichier image (JPEG, PNG, WebP, GIF, max 10 Mo)')
                    ],
                    required: ['file']
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Logo uploadé', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 422, description: 'Fichier invalide', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 404, description: 'Organisation introuvable', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function uploadOrgLogo(string $uuid, Request $request): JsonResponse
    {
        $file = $request->files->get('file');

        if (!$file) {
            return $this->json(
                (new Feedback())->addError('file', 'Aucun fichier fourni.')->setErrorFlushDescription('Fichier requis.')->setStatus(422)->autoInitFlush(),
                422
            );
        }

        $organization = $this->mediaService->resolveOrganization($uuid);

        if ($organization === null) {
            return $this->json(
                (new Feedback())->setErrorFlushDescription('Organisation introuvable.')->setStatus(404)->autoInitFlush(),
                404
            );
        }

        try {
            $relativePath = $this->mediaService->replaceOrganizationLogo($organization, $file);
        } catch (\InvalidArgumentException $e) {
            return $this->json(
                (new Feedback())->addError('file', $e->getMessage())->setErrorFlushDescription($e->getMessage())->setStatus(422)->autoInitFlush(),
                422
            );
        }

        return $this->json(
            (new Feedback())->setData([
                'path' => $relativePath,
                'url' => $this->fileUploadService->getPublicUrl($relativePath),
            ])->setFlushDescription('Logo mis à jour.')->setStatus(200)->autoInitFlush(),
            200
        );
    }

    #[Route('/organizations/{uuid}/logo', name: 'org_logo_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/v1/media/organizations/{uuid}/logo',
        summary: 'Supprimer le logo d\'une organisation',
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Logo supprimé', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 404, description: 'Organisation introuvable', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function deleteOrgLogo(string $uuid): JsonResponse
    {
        $organization = $this->mediaService->resolveOrganization($uuid);

        if ($organization === null) {
            return $this->json(
                (new Feedback())->setErrorFlushDescription('Organisation introuvable.')->setStatus(404)->autoInitFlush(),
                404
            );
        }

        $removed = $this->mediaService->removeOrganizationLogo($organization);

        $feedback = new Feedback();

        if (!$removed) {
            return $this->json(
                $feedback->setErrorFlushDescription('Aucun logo à supprimer.')->setStatus(404)->autoInitFlush(),
                404
            );
        }

        return $this->json(
            $feedback->setFlushDescription('Logo supprimé.')->setStatus(200)->autoInitFlush(),
            200
        );
    }

    // ==================== PARCEL PHOTOS ====================

    #[Route('/parcels/{uuid}/photos', name: 'parcel_photos_upload', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/media/parcels/{uuid}/photos',
        summary: 'Uploader des photos pour une parcelle',
        description: 'Ajoute des photos dans le dossier de la parcelle. Types acceptés : JPEG, PNG, WebP, GIF. Max 10 Mo.',
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            description: 'Fichier(s) (multipart/form-data)',
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'files', type: 'array', items: new OA\Items(type: 'string', format: 'binary'), description: 'Fichiers images (max 10 Mo chacun)')
                    ],
                    required: ['files']
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Fichiers uploadés', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 422, description: 'Fichier(s) invalide(s)', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 404, description: 'Parcelle introuvable', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function uploadParcelPhotos(string $uuid, Request $request): JsonResponse
    {
        $files = $request->files->get('files');

        if (!$files || !is_array($files)) {
            return $this->json(
                (new Feedback())->addError('files', 'Aucun fichier fourni.')->setErrorFlushDescription('Fichiers requis.')->setStatus(422)->autoInitFlush(),
                422
            );
        }

        $parcel = $this->mediaService->resolveParcel($uuid);

        if ($parcel === null) {
            return $this->json(
                (new Feedback())->setErrorFlushDescription('Parcelle introuvable.')->setStatus(404)->autoInitFlush(),
                404
            );
        }

        $uploaded = [];
        $errors = [];

        try {
            $uploaded = $this->mediaService->addParcelPhotos($parcel, $files);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $errors[] = ['file' => null, 'error' => $e->getMessage()];
        }

        $feedback = new Feedback();
        $feedback->setData([
            'uploaded' => $uploaded,
            'errors' => $errors,
        ]);

        if ([] === $uploaded) {
            $feedback->setErrorFlushDescription('Aucun fichier uploadé.')->setStatus(422);
        } else {
            $feedback->setFlushDescription(sprintf('%d fichier(s) uploadé(s).', count($uploaded)))->setStatus(200);
        }

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/parcels/{uuid}/photos/{filename}', name: 'parcel_photo_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/v1/media/parcels/{uuid}/photos/{filename}',
        summary: 'Supprimer une photo d\'une parcelle',
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'filename', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Fichier supprimé', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 404, description: 'Fichier introuvable', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function deleteParcelPhoto(string $uuid, string $filename): JsonResponse
    {
        $parcel = $this->mediaService->resolveParcel($uuid);

        if ($parcel === null) {
            return $this->json(
                (new Feedback())->setErrorFlushDescription('Parcelle introuvable.')->setStatus(404)->autoInitFlush(),
                404
            );
        }

        $deleted = $this->mediaService->deleteParcelPhoto($parcel, $filename);

        $feedback = new Feedback();

        if ($deleted) {
            $feedback->setFlushDescription('Fichier supprimé.')->setStatus(200);
        } else {
            $feedback->setErrorFlushDescription('Fichier introuvable.')->setStatus(404);
        }

        return $this->json($feedback, $feedback->getStatus());
    }
}
