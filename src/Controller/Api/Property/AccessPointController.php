<?php

declare(strict_types=1);

namespace App\Controller\Api\Property;

use App\Dto\Feedback;
use App\Dto\Request\Property\AccessPointRequest;
use App\Dto\Request\Property\AccessPointFilterDto;
use App\Dto\Request\Property\AccessPointStatusRequest;
use App\Service\Property\AccessPointService;
use App\Trait\FeedbackTrait;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/access-points', name: 'api_access_points_')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Access points', description: 'Accès physiques de bâtiments, partagés ou privés.')]
final class AccessPointController extends AbstractController
{
    use FeedbackTrait;

    public function __construct(private readonly AccessPointService $service) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(#[MapQueryString] ?AccessPointFilterDto $filter = null): JsonResponse
    {
        $buildingUuid = $filter?->buildingUuid;
        $parcelUuid = $filter?->parcelUuid;
        $feedback = $buildingUuid === null && $parcelUuid === null
            ? (new Feedback())->addError('parcelUuid', 'Indiquez une parcelle ou un bâtiment.')->autoInitFlush()->setStatus(422)
            : $this->service->list($buildingUuid, $parcelUuid);
        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(summary: 'Créer un accès de bâtiment', requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: AccessPointRequest::class))), responses: [new OA\Response(response: 201, description: 'Accès créé', content: new OA\JsonContent(ref: new Model(type: Feedback::class)))])]
    public function create(#[MapRequestPayload] AccessPointRequest $request): JsonResponse
    {
        $feedback = $this->service->create($request, $this->getUser());
        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(string $uuid, #[MapRequestPayload] AccessPointRequest $request): JsonResponse
    {
        $feedback = $this->service->update($uuid, $request, $this->getUser());
        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}/status', name: 'status', methods: ['PATCH'])]
    public function status(string $uuid, #[MapRequestPayload] AccessPointStatusRequest $request): JsonResponse
    {
        $feedback = $this->service->setEnabled($uuid, $request->enabled, $this->getUser());
        return $this->json($feedback, $feedback->getStatus());
    }
}
