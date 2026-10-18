<?php

declare(strict_types=1);

namespace App\Controller\Api\System;

use App\Dto\Feedback;
use App\Dto\Request\System\PrivateDocumentFilterDto;
use App\Entity\Identity\User;
use App\Service\System\PrivateDocumentService;
use App\Trait\FeedbackTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/documents', name: 'api_private_documents_')]
#[IsGranted('ROLE_USER')]
final class PrivateDocumentController extends AbstractController
{
    use FeedbackTrait;

    public function __construct(private readonly PrivateDocumentService $documents)
    {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(#[MapQueryString(validationFailedStatusCode: 422)] PrivateDocumentFilterDto $filter): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json((new Feedback())->setErrorFlushDescription('Utilisateur non authentifié.')->setStatus(401)->autoInitFlush(), 401);
        }
        try {
            $data = $this->documents->list($user, $filter->organizationUuid, $filter->cityUuid, $filter->relatedType, $filter->relatedUuid);
        } catch (\InvalidArgumentException $error) {
            return $this->json((new Feedback())->addError('filter', $error->getMessage())->setErrorFlushDescription($error->getMessage())->setStatus(422)->autoInitFlush(), 422);
        }

        return $this->json((new Feedback())->setData($data)->setStatus(200)->autoInitFlush(), 200);
    }

    #[Route('', name: 'upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json((new Feedback())->setErrorFlushDescription('Utilisateur non authentifié.')->setStatus(401)->autoInitFlush(), 401);
        }

        $description = $request->request->get('description');
        if ($description !== null && !is_string($description)) {
            return $this->json((new Feedback())->addError('description', 'Description invalide.')->setErrorFlushDescription('Description invalide.')->setStatus(422)->autoInitFlush(), 422);
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return $this->json((new Feedback())->addError('file', 'Aucun fichier fourni.')->setErrorFlushDescription('Fichier requis.')->setStatus(422)->autoInitFlush(), 422);
        }

        $organizationUuid = $request->request->get('organizationUuid', '');
        $relatedType = $request->request->get('relatedType', '');
        $relatedUuid = $request->request->get('relatedUuid', '');
        $category = $request->request->get('category', '');
        foreach (['organizationUuid' => $organizationUuid, 'relatedType' => $relatedType, 'relatedUuid' => $relatedUuid, 'category' => $category] as $field => $value) {
            if (!is_string($value)) {
                return $this->json((new Feedback())->addError($field, 'Cette valeur doit être un texte simple.')->setErrorFlushDescription('Certaines données du formulaire de document sont invalides.')->setStatus(422)->autoInitFlush(), 422);
            }
        }

        try {
            $document = $this->documents->upload(
                $user,
                $organizationUuid,
                $relatedType,
                $relatedUuid,
                $category,
                $description,
                $file,
            );
        } catch (\InvalidArgumentException $error) {
            return $this->json((new Feedback())->addError('document', $error->getMessage())->setErrorFlushDescription($error->getMessage())->setStatus(422)->autoInitFlush(), 422);
        }

        return $this->json((new Feedback())->setData($document)->setFlushDescription('Document ajouté en espace privé.')->setStatus(201)->autoInitFlush(), 201);
    }

    #[Route('/{uuid}/download', name: 'download', methods: ['GET'])]
    public function download(string $uuid): BinaryFileResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non authentifié.');
        }
        $file = $this->documents->download($user, $uuid);
        $response = new BinaryFileResponse($file['path']);
        $response->headers->set('Content-Type', $file['mimeType']);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file['name']);

        return $response;
    }

    #[Route('/{uuid}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $uuid): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json((new Feedback())->setErrorFlushDescription('Utilisateur non authentifié.')->setStatus(401)->autoInitFlush(), 401);
        }
        $this->documents->delete($user, $uuid);

        return $this->json((new Feedback())->setFlushDescription('Document supprimé.')->setStatus(200)->autoInitFlush(), 200);
    }
}
