<?php

declare(strict_types=1);

namespace App\Controller\Api\System;

use App\Dto\Request\System\PublicationDraftDto;
use App\Enum\PublicationStatus;
use App\Service\System\PublicationService;
use App\Trait\FeedbackTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

#[Route('/api/v1/publications')]
#[IsGranted('ROLE_USER')]
final class PublicationController extends AbstractController
{
    use FeedbackTrait;

    public function __construct(private readonly PublicationService $publications) {}

    #[Route('/management', name: 'app_publications_management', methods: ['GET'])]
    public function management(): Response
    {
        return $this->respondSuccess($this->publications->managementList(), 'Publications chargées.');
    }

    #[Route('/active', name: 'app_publications_active', methods: ['GET'])]
    public function active(): Response
    {
        return $this->respondSuccess($this->publications->activeForCurrentUser(), 'Notifications chargées.');
    }

    #[Route('', name: 'app_publications_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] PublicationDraftDto $dto): Response
    {
        return $this->respondSuccess($this->publications->saveDraft($dto), 'Brouillon enregistré.', Response::HTTP_CREATED);
    }

    #[Route('/{uuid}', name: 'app_publications_update', methods: ['PUT'])]
    public function update(string $uuid, #[MapRequestPayload] PublicationDraftDto $dto): Response
    {
        return $this->respondSuccess($this->publications->saveDraft($dto, $uuid), 'Brouillon mis à jour.');
    }

    #[Route('/{uuid}/publish', name: 'app_publications_publish', methods: ['POST'])]
    public function publish(string $uuid): Response
    {
        return $this->respondSuccess($this->publications->publish($uuid), 'Publication diffusée.');
    }

    #[Route('/{uuid}/archive', name: 'app_publications_archive', methods: ['POST'])]
    public function archive(string $uuid): Response
    {
        return $this->respondSuccess($this->publications->changeStatus($uuid, PublicationStatus::ARCHIVED), 'Publication archivée.');
    }

    #[Route('/{uuid}/withdraw', name: 'app_publications_withdraw', methods: ['POST'])]
    public function withdraw(string $uuid): Response
    {
        return $this->respondSuccess($this->publications->changeStatus($uuid, PublicationStatus::WITHDRAWN), 'Publication retirée de la diffusion.');
    }

    #[Route('/{uuid}/images', name: 'app_publications_images', methods: ['POST'])]
    public function images(string $uuid, Request $request): Response
    {
        $files = $request->files->all('files');
        $captions = $request->request->all('captions');
        return $this->respondSuccess($this->publications->addImages($uuid, $files, $captions), 'Images ajoutées.');
    }

    #[Route('/{uuid}', name: 'app_publications_show', methods: ['GET'])]
    public function show(string $uuid): Response
    {
        return $this->respondSuccess($this->publications->openForCurrentUser($uuid), 'Publication consultée.');
    }
}
