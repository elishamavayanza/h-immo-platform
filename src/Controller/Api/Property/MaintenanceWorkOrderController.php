<?php

declare(strict_types=1);

namespace App\Controller\Api\Property;

use App\Dto\Feedback;
use App\Dto\Request\Property\MaintenanceWorkOrderFilter;
use App\Dto\Request\Property\MaintenanceWorkOrderRequest;
use App\Dto\Request\Property\MaintenanceWorkOrderStatusRequest;
use App\Entity\Identity\User;
use App\Service\Property\MaintenanceWorkOrderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/maintenance/work-orders', name: 'api_maintenance_work_orders_')]
#[IsGranted('ROLE_USER')]
final class MaintenanceWorkOrderController extends AbstractController
{
    public function __construct(private readonly MaintenanceWorkOrderService $workOrders)
    {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(#[MapQueryString] ?MaintenanceWorkOrderFilter $filter = null): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User || $filter === null) {
            return $this->feedbackError('Un filtre de ville valide est requis.', 422);
        }
        try {
            $data = $this->workOrders->list($user, $filter->organizationUuid, $filter->cityUuid, $filter->status);
        } catch (\InvalidArgumentException $error) {
            return $this->feedbackError($error->getMessage(), 422);
        }

        return $this->json((new Feedback())->setData($data)->setStatus(200)->autoInitFlush(), 200);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload] MaintenanceWorkOrderRequest $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->feedbackError('Utilisateur non authentifié.', 401);
        }
        try {
            $data = $this->workOrders->create($user, $request);
        } catch (\InvalidArgumentException $error) {
            return $this->feedbackError($error->getMessage(), 422);
        }

        return $this->json((new Feedback())->setData($data)->setFlushDescription('Intervention créée.')->setStatus(201)->autoInitFlush(), 201);
    }

    #[Route('/{uuid}', name: 'update', methods: ['PUT'])]
    public function update(string $uuid, #[MapRequestPayload] MaintenanceWorkOrderRequest $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->feedbackError('Utilisateur non authentifié.', 401);
        }
        try {
            $data = $this->workOrders->update($user, $uuid, $request);
        } catch (\InvalidArgumentException $error) {
            return $this->feedbackError($error->getMessage(), 422);
        }

        return $this->json((new Feedback())->setData($data)->setFlushDescription('Intervention mise à jour.')->setStatus(200)->autoInitFlush(), 200);
    }

    #[Route('/{uuid}/status', name: 'transition', methods: ['PATCH'])]
    public function transition(string $uuid, #[MapRequestPayload] MaintenanceWorkOrderStatusRequest $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->feedbackError('Utilisateur non authentifié.', 401);
        }
        try {
            $data = $this->workOrders->transition($user, $uuid, $request->organizationUuid, $request->cityUuid, $request->status, $request->completedAt);
        } catch (\InvalidArgumentException $error) {
            return $this->feedbackError($error->getMessage(), 422);
        }

        return $this->json((new Feedback())->setData($data)->setFlushDescription('État de l’intervention mis à jour.')->setStatus(200)->autoInitFlush(), 200);
    }

    private function feedbackError(string $message, int $status): JsonResponse
    {
        $feedback = (new Feedback())->addError('maintenance', $message)->setErrorFlushDescription($message)->setStatus($status)->autoInitFlush();

        return $this->json($feedback, $status);
    }
}
