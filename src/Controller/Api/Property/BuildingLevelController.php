<?php

declare(strict_types=1);

namespace App\Controller\Api\Property;

use App\Dto\Request\Property\BuildingLevelRequest;
use App\Service\Property\BuildingLevelService;
use App\Trait\FeedbackTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class BuildingLevelController extends AbstractController
{
    use FeedbackTrait;

    public function __construct(private readonly BuildingLevelService $service) {}

    #[Route('/api/v1/property/buildings/{buildingUuid}/levels', name: 'api_building_levels_list', methods: ['GET'])]
    public function list(string $buildingUuid): JsonResponse
    {
        $feedback = $this->service->list($buildingUuid);
        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/api/v1/property/buildings/{buildingUuid}/levels', name: 'api_building_levels_create', methods: ['POST'])]
    public function create(string $buildingUuid, #[MapRequestPayload] BuildingLevelRequest $request): JsonResponse
    {
        $feedback = $this->service->create($buildingUuid, $request, $this->getUser());
        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/api/v1/property/building-levels/{uuid}', name: 'api_building_levels_update', methods: ['PUT', 'PATCH'])]
    public function update(string $uuid, #[MapRequestPayload] BuildingLevelRequest $request): JsonResponse
    {
        $feedback = $this->service->update($uuid, $request, $this->getUser());
        return $this->json($feedback, $feedback->getStatus());
    }
}
