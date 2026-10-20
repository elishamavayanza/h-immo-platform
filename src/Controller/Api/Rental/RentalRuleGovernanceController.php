<?php

declare(strict_types=1);

namespace App\Controller\Api\Rental;

use App\Dto\Feedback;
use App\Dto\Request\Rental\RentalRuleDelegationRequest;
use App\Dto\Request\Rental\RentalRuleDelegationRevokeRequest;
use App\Dto\Request\Rental\RentalRuleDraftRequest;
use App\Service\Rental\RentalRuleGovernanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** API de gouvernance des règles locatives; toute autorisation métier est vérifiée par le service central. */
#[Route('/api/v1/organizations/{organizationUuid}/rental-rules', name: 'api_rental_rules_')]
#[IsGranted('ROLE_USER')]
final class RentalRuleGovernanceController extends AbstractController
{
    public function __construct(private readonly RentalRuleGovernanceService $service) {}

    #[Route('/capabilities', name: 'capabilities', methods: ['GET'])]
    public function capabilities(string $organizationUuid): JsonResponse { return $this->respond($this->service->currentCapabilities($organizationUuid)); }

    #[Route('/versions', name: 'versions', methods: ['GET'])]
    public function versions(string $organizationUuid): JsonResponse { return $this->respond($this->service->listVersions($organizationUuid)); }

    #[Route('/drafts', name: 'draft_list', methods: ['GET'])]
    public function drafts(string $organizationUuid): JsonResponse { return $this->respond($this->service->listDrafts($organizationUuid)); }

    #[Route('/drafts', name: 'draft_create', methods: ['POST'])]
    public function createDraft(string $organizationUuid, #[MapRequestPayload] RentalRuleDraftRequest $request): JsonResponse
    { return $this->respond($this->service->createDraft($organizationUuid, $request)); }

    #[Route('/drafts/{draftUuid}', name: 'draft_update', methods: ['PUT'])]
    public function updateDraft(string $organizationUuid, string $draftUuid, #[MapRequestPayload] RentalRuleDraftRequest $request): JsonResponse
    { return $this->respond($this->service->updateDraft($organizationUuid, $draftUuid, $request)); }

    #[Route('/drafts/{draftUuid}/publish', name: 'draft_publish', methods: ['POST'])]
    public function publishDraft(string $organizationUuid, string $draftUuid): JsonResponse
    { return $this->respond($this->service->publishDraft($organizationUuid, $draftUuid)); }

    #[Route('/delegations', name: 'delegation_grant', methods: ['POST'])]
    public function grant(string $organizationUuid, #[MapRequestPayload] RentalRuleDelegationRequest $request): JsonResponse
    { return $this->respond($this->service->grantPublication($organizationUuid, $request->userUuid, $request->expiresAt)); }

    #[Route('/delegations', name: 'delegation_list', methods: ['GET'])]
    public function delegations(string $organizationUuid): JsonResponse { return $this->respond($this->service->listDelegations($organizationUuid)); }

    #[Route('/delegations/{delegationUuid}/revoke', name: 'delegation_revoke', methods: ['POST'])]
    public function revoke(string $organizationUuid, string $delegationUuid, #[MapRequestPayload] RentalRuleDelegationRevokeRequest $request): JsonResponse
    { return $this->respond($this->service->revokePublication($organizationUuid, $delegationUuid, $request->reason)); }

    private function respond(Feedback $feedback): JsonResponse { return $this->json($feedback, $feedback->getStatus()); }
}
