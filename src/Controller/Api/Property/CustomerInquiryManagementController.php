<?php

declare(strict_types=1);

namespace App\Controller\Api\Property;

use App\Dto\Feedback;
use App\Dto\Request\Property\CustomerInquiryFilterRequest;
use App\Dto\Request\Property\CustomerInquiryTransitionRequest;
use App\Dto\Request\Property\CustomerVisitRequest;
use App\Dto\Request\Property\CustomerVisitTransitionRequest;
use App\Service\Property\CustomerInquiryService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/organizations/{organizationUuid}/inquiries', name: 'api_customer_inquiry_')]
#[OA\Tag(name: 'Customer Inquiries', description: 'Suivi des demandes dans le périmètre autorisé de l’organisation et des villes attribuées.')]
final class CustomerInquiryManagementController extends AbstractController
{
    public function __construct(private readonly CustomerInquiryService $inquiries)
    {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(summary: 'Lister les demandes client autorisées', parameters: [
        new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['new', 'in_progress', 'contacted', 'visit_proposed', 'visit_completed', 'negotiation', 'converted', 'closed'])),
        new OA\Parameter(name: 'type', in: 'query', schema: new OA\Schema(type: 'string', enum: ['information', 'visit'])),
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
        new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20)),
    ])]
    #[OA\Response(response: 200, description: 'Liste bornée à l’organisation et aux villes attribuées au rôle ADMIN_VILLE', content: new OA\JsonContent(ref: new Model(type: Feedback::class)))]
    public function list(string $organizationUuid, #[MapQueryString(validationFailedStatusCode: 400)] ?CustomerInquiryFilterRequest $filter = null): JsonResponse
    {
        return $this->respond($this->inquiries->listForOrganization($organizationUuid, $filter ?? new CustomerInquiryFilterRequest()));
    }

    #[Route('/notifications', name: 'notifications', methods: ['GET'])]
    #[OA\Get(summary: 'Charger les alertes personnelles de demandes client')]
    public function notifications(string $organizationUuid): JsonResponse
    {
        return $this->respond($this->inquiries->notificationsForCurrentUser($organizationUuid));
    }

    #[Route('/notifications/{notificationUuid}/read', name: 'notification_read', methods: ['POST'])]
    #[OA\Post(summary: 'Marquer comme lue une alerte personnelle de demande client')]
    public function markNotificationRead(string $organizationUuid, string $notificationUuid): JsonResponse
    {
        $this->inquiries->markNotificationRead($organizationUuid, $notificationUuid);
        return $this->respond(['read' => true]);
    }

    #[Route('/{inquiryUuid}', name: 'show', methods: ['GET'])]
    #[OA\Get(summary: 'Consulter une demande dans son organisation')]
    public function show(string $organizationUuid, string $inquiryUuid): JsonResponse
    {
        return $this->respond($this->inquiries->get($organizationUuid, $inquiryUuid));
    }

    #[Route('/{inquiryUuid}/history', name: 'history', methods: ['GET'])]
    #[OA\Get(summary: 'Consulter l’historique immuable d’une demande')]
    public function history(string $organizationUuid, string $inquiryUuid): JsonResponse
    {
        return $this->respond($this->inquiries->history($organizationUuid, $inquiryUuid));
    }

    #[Route('/{inquiryUuid}/take-ownership', name: 'take_ownership', methods: ['POST'])]
    #[OA\Post(summary: 'Prendre en charge une nouvelle demande')]
    public function takeOwnership(string $organizationUuid, string $inquiryUuid): JsonResponse
    {
        return $this->respond($this->inquiries->takeOwnership($organizationUuid, $inquiryUuid));
    }

    #[Route('/{inquiryUuid}/transition', name: 'transition', methods: ['POST'])]
    #[OA\Post(summary: 'Effectuer une transition autorisée de suivi')]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: CustomerInquiryTransitionRequest::class)))]
    public function transition(string $organizationUuid, string $inquiryUuid, #[MapRequestPayload] CustomerInquiryTransitionRequest $request): JsonResponse
    {
        return $this->respond($this->inquiries->transition($organizationUuid, $inquiryUuid, $request->status, $request->reason));
    }

    #[Route('/{inquiryUuid}/visits', name: 'visits', methods: ['GET'])]
    #[OA\Get(summary: 'Lister les rendez-vous liés à une demande')]
    public function visits(string $organizationUuid, string $inquiryUuid): JsonResponse
    {
        return $this->respond($this->inquiries->listVisitsForInquiry($organizationUuid, $inquiryUuid));
    }

    #[Route('/{inquiryUuid}/visits', name: 'propose_visit', methods: ['POST'])]
    #[OA\Post(summary: 'Proposer un rendez-vous sans le confirmer')]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: CustomerVisitRequest::class)))]
    public function proposeVisit(string $organizationUuid, string $inquiryUuid, #[MapRequestPayload] CustomerVisitRequest $request): JsonResponse
    {
        return $this->respond($this->inquiries->proposeVisit($organizationUuid, $inquiryUuid, $request), 201);
    }

    #[Route('/{inquiryUuid}/visits/{visitUuid}/reschedule', name: 'reschedule_visit', methods: ['PATCH'])]
    #[OA\Patch(summary: 'Reporter un rendez-vous encore ouvert')]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: CustomerVisitRequest::class)))]
    public function rescheduleVisit(string $organizationUuid, string $inquiryUuid, string $visitUuid, #[MapRequestPayload] CustomerVisitRequest $request): JsonResponse
    {
        return $this->respond($this->inquiries->rescheduleVisit($organizationUuid, $inquiryUuid, $visitUuid, $request));
    }

    #[Route('/{inquiryUuid}/visits/{visitUuid}/transition', name: 'transition_visit', methods: ['PATCH'])]
    #[OA\Patch(summary: 'Confirmer, terminer ou annuler un rendez-vous')]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: CustomerVisitTransitionRequest::class)))]
    public function transitionVisit(string $organizationUuid, string $inquiryUuid, string $visitUuid, #[MapRequestPayload] CustomerVisitTransitionRequest $request): JsonResponse
    {
        return $this->respond($this->inquiries->transitionVisit($organizationUuid, $inquiryUuid, $visitUuid, $request));
    }

    #[Route('/{inquiryUuid}/convert-to-lease', name: 'convert_to_lease', methods: ['POST'])]
    #[OA\Post(summary: 'Rattacher une demande en négociation à un bail actif existant')]
    public function convertToLease(string $organizationUuid, string $inquiryUuid): JsonResponse
    {
        return $this->respond($this->inquiries->convertToActiveLease($organizationUuid, $inquiryUuid));
    }

    private function respond(mixed $data, int $status = 200): JsonResponse
    {
        $feedback = (new Feedback())->setData($data)->setFlushDescription('Demande traitée.')->setStatus($status)->autoInitFlush();
        return $this->json($feedback, $status);
    }
}
