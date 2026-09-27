<?php

declare(strict_types=1);

namespace App\Controller\Api\System;

use App\Dto\Feedback;
use App\Service\System\FileUploadService;
use App\Trait\FeedbackTrait;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * MediaController
 *
 * Package : System & Audit
 *
 * Gestion des médias (photos, logos, documents) pour les entités du système.
 * Les fichiers sont stockés dans public/uploads/{type}/ et accessibles via /uploads/{type}/{file}.
 *
 * Authentification requise (ROLE_USER).
 * Les permissions fines (qui peut uploader quoi) sont vérifiées dans les services métier.
 */
#[Route('/api/v1/media', name: 'api_media_')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Media', description: 'Gestion des médias (photos, logos, documents) pour les entités du système.')]
final class MediaController extends AbstractController
{
    use FeedbackTrait;

    public function __construct(
        private readonly FileUploadService $fileUploadService,
    ) {
    }

    // ==================== USER PROFILE PHOTO ====================

    #[Route('/users/{uuid}/photo', name: 'user_photo_upload', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/media/users/{uuid}/photo',
        summary: 'Uploader la photo de profil d\'un utilisateur',
        description: 'Remplace la photo existante. Types acceptés : JPEG, PNG, WebP, GIF. Max 10 Mo.',
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
            new OA\Response(response: 200, description: 'Photo uploadée', content: new OA\JsonContent(ref: Feedback::class)),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: Feedback::class)),
            new OA\Response(response: 422, description: 'Fichier invalide', content: new OA\JsonContent(ref: Feedback::class)),
            new OA\Response(response: 404, description: 'Utilisateur introuvable', content: new OA\JsonContent(ref: Feedback::class)),
        ]
    )]
    public function uploadUserPhoto(string $uuid, Request $request): JsonResponse
    {
        $file = $request->files->get('file');

        if (!$file) {
            $feedback = new Feedback();
            return $this->json(
                $feedback->addError('file', 'Aucun fichier fourni.')->setErrorFlushDescription('Fichier requis.')->setStatus(422)->autoInitFlush(),
                422
            );
        }

        // TODO: Vérifier que l'utilisateur a le droit de modifier cette photo
        // (soit c'est son propre profil, soit il a les droits d'admin)

        try {
            // Récupérer l'ancien chemin pour suppression
            // $oldPath = ... depuis l'entité User
            $relativePath = $this->fileUploadService->upload($file, 'users');

            // TODO: Mettre à jour l'entité User avec le nouveau chemin
            // $user->setProfilePhoto($relativePath);

            $feedback = new Feedback();
            $feedback->setData([
                'path' => $relativePath,
                'url' => $this->fileUploadService->getPublicUrl($relativePath),
            ])->setFlushDescription('Photo de profil mise à jour.')->setStatus(200)->autoInitFlush();

            return $this->json($feedback, 200);
        } catch (\InvalidArgumentException $e) {
            $feedback = new Feedback();
            return $this->json($feedback->addError('file', $e->getMessage())->setErrorFlushDescription($e->getMessage())->setStatus(422)->autoInitFlush(), 422);
        } catch (\RuntimeException $e) {
            $feedback = new Feedback();
            return $this->json($feedback->setErrorFlushDescription('Erreur lors de l\'upload : ' . $e->getMessage())->setStatus(500)->autoInitFlush(), 500);
        }
    }

    #[Route('/users/{uuid}/photo', name: 'user_photo_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/v1/media/users/{uuid}/photo',
        summary: 'Supprimer la photo de profil d\'un utilisateur',
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Photo supprimée', content: new OA\JsonContent(ref: Feedback::class)),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: Feedback::class)),
            new OA\Response(response: 404, description: 'Utilisateur introuvable', content: new OA\JsonContent(ref: Feedback::class)),
        ]
    )]
    public function deleteUserPhoto(string $uuid): JsonResponse
    {
        // TODO: Récupérer l'ancien chemin depuis l'entité User
        // $this->fileUploadService->delete($oldPath);
        // $user->setProfilePhoto(null);

        $feedback = new Feedback();
        return $this->json($feedback->setFlushDescription('Photo supprimée (à implémenter).')->setStatus(200)->autoInitFlush(), 200);
    }

    // ==================== ORGANIZATION LOGO ====================

    #[Route('/organizations/{uuid}/logo', name: 'org_logo_upload', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/media/organizations/{uuid}/logo',
        summary: 'Uploader le logo d\'une organisation',
        description: 'Remplace le logo existant. Types acceptés : JPEG, PNG, WebP, GIF. Max 10 Mo.',
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
            new OA\Response(response: 200, description: 'Logo uploadé', content: new OA\JsonContent(ref: Feedback::class)),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: Feedback::class)),
            new OA\Response(response: 422, description: 'Fichier invalide', content: new OA\JsonContent(ref: Feedback::class)),
            new OA\Response(response: 404, description: 'Organisation introuvable', content: new OA\JsonContent(ref: Feedback::class)),
        ]
    )]
    public function uploadOrgLogo(string $uuid, Request $request): JsonResponse
    {
        $file = $request->files->get('file');

        if (!$file) {
            $feedback = new Feedback();
            return $this->json(
                $feedback->addError('file', 'Aucun fichier fourni.')->setErrorFlushDescription('Fichier requis.')->setStatus(422)->autoInitFlush(),
                422
            );
        }

        // TODO: Vérifier droits (PATRON/ADMIN_IMMOBILIER de l'org)

        try {
            $relativePath = $this->fileUploadService->upload($file, 'organizations');

            // TODO: Mettre à jour Organization->logo

            $feedback = new Feedback();
            $feedback->setData([
                'path' => $relativePath,
                'url' => $this->fileUploadService->getPublicUrl($relativePath),
            ])->setFlushDescription('Logo mis à jour.')->setStatus(200)->autoInitFlush();

            return $this->json($feedback, 200);
        } catch (\InvalidArgumentException $e) {
            $feedback = new Feedback();
            return $this->json($feedback->addError('file', $e->getMessage())->setErrorFlushDescription($e->getMessage())->setStatus(422)->autoInitFlush(), 422);
        } catch (\RuntimeException $e) {
            $feedback = new Feedback();
            return $this->json($feedback->setErrorFlushDescription('Erreur lors de l\'upload : ' . $e->getMessage())->setStatus(500)->autoInitFlush(), 500);
        }
    }

    #[Route('/organizations/{uuid}/logo', name: 'org_logo_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/v1/media/organizations/{uuid}/logo',
        summary: 'Supprimer le logo d\'une organisation',
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Logo supprimé', content: new OA\JsonContent(ref: Feedback::class)),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: Feedback::class)),
            new OA\Response(response: 404, description: 'Organisation introuvable', content: new OA\JsonContent(ref: Feedback::class)),
        ]
    )]
    public function deleteOrgLogo(string $uuid): JsonResponse
    {
        $feedback = new Feedback();
        return $this->json($feedback->setFlushDescription('Logo supprimé (à implémenter).')->setStatus(200)->autoInitFlush(), 200);
    }

    // ==================== PARCEL PHOTOS/DOCUMENTS ====================

    #[Route('/parcels/{uuid}/photos', name: 'parcel_photos_upload', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/media/parcels/{uuid}/photos',
        summary: 'Uploader des photos/documents pour une parcelle',
        description: 'Ajoute des photos ou documents. Types acceptés : JPEG, PNG, WebP, GIF, PDF. Max 10 Mo.',
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
                        new OA\Property(property: 'files', type: 'array', items: new OA\Items(type: 'string', format: 'binary'), description: 'Fichiers images ou PDF (max 10 Mo chacun)')
                    ],
                    required: ['files']
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Fichiers uploadés', content: new OA\JsonContent(ref: Feedback::class)),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: Feedback::class)),
            new OA\Response(response: 422, description: 'Fichier(s) invalide(s)', content: new OA\JsonContent(ref: Feedback::class)),
            new OA\Response(response: 404, description: 'Parcelle introuvable', content: new OA\JsonContent(ref: Feedback::class)),
        ]
    )]
    public function uploadParcelPhotos(string $uuid, Request $request): JsonResponse
    {
        $files = $request->files->get('files');

        if (!$files || !is_array($files)) {
            $feedback = new Feedback();
            return $this->json(
                $feedback->addError('files', 'Aucun fichier fourni.')->setErrorFlushDescription('Fichiers requis.')->setStatus(422)->autoInitFlush(),
                422
            );
        }

        // TODO: Vérifier droits

        $uploaded = [];
        $errors = [];

        foreach ($files as $file) {
            try {
                $relativePath = $this->fileUploadService->upload($file, 'parcels');
                $uploaded[] = [
                    'path' => $relativePath,
                    'url' => $this->fileUploadService->getPublicUrl($relativePath),
                ];
            } catch (\InvalidArgumentException $e) {
                $errors[] = ['file' => $file->getClientOriginalName(), 'error' => $e->getMessage()];
            } catch (\RuntimeException $e) {
                $errors[] = ['file' => $file->getClientOriginalName(), 'error' => $e->getMessage()];
            }
        }

        $feedback = new Feedback();
        $feedback->setData([
            'uploaded' => $uploaded,
            'errors' => $errors,
        ]);

        if (empty($uploaded)) {
            $feedback->setErrorFlushDescription('Aucun fichier uploadé.')->setStatus(422);
        } else {
            $feedback->setFlushDescription(sprintf('%d fichier(s) uploadé(s).', count($uploaded)))->setStatus(200);
        }

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/parcels/{uuid}/photos/{filename}', name: 'parcel_photo_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/v1/media/parcels/{uuid}/photos/{filename}',
        summary: 'Supprimer une photo/document d\'une parcelle',
        parameters: [
            new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'filename', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Fichier supprimé', content: new OA\JsonContent(ref: Feedback::class)),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: Feedback::class)),
            new OA\Response(response: 404, description: 'Fichier introuvable', content: new OA\JsonContent(ref: Feedback::class)),
        ]
    )]
    public function deleteParcelPhoto(string $uuid, string $filename): JsonResponse
    {
        $relativePath = 'parcels/' . $filename;
        $deleted = $this->fileUploadService->delete($relativePath);

        $feedback = new Feedback();
        if ($deleted) {
            $feedback->setFlushDescription('Fichier supprimé.')->setStatus(200);
        } else {
            $feedback->setErrorFlushDescription('Fichier introuvable.')->setStatus(404);
        }

        return $this->json($feedback, $feedback->getStatus());
    }
}