<?php

declare(strict_types=1);

namespace App\Controller\Api\Property;

use App\Dto\Feedback;
use App\Dto\Request\Property\ListingCandidateFilterRequest;
use App\Dto\Request\Property\ListingDecisionRequest;
use App\Dto\Request\Property\ListingDraftRequest;
use App\Dto\Request\Property\ListingFilterRequest;
use App\Service\Property\ListingWorkflowService;
use App\Trait\FeedbackTrait;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

/** API du circuit de validation des annonces de la vitrine immobilière. */
#[Route('/api/v1', name: 'api_listing_')]
#[OA\Tag(name: 'Property listings', description: 'Brouillons, contrôle organisationnel et publication publique des annonces.')]
final class ListingController extends AbstractController
{
    use FeedbackTrait;

    public function __construct(private readonly ListingWorkflowService $workflow) {}

    #[Route('/organizations/{organizationUuid}/listings', name: 'list', methods: ['GET'])]
    #[OA\Get(summary: 'Lister les annonces internes d’une organisation', parameters: [
        new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['draft', 'pending_review', 'corrections_requested', 'pending_publication', 'published', 'rejected', 'withdrawn'])),
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
        new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20)),
        new OA\Parameter(name: 'cityUuid', in: 'query', description: 'Ville active pour ADMIN_VILLE; l’UUID est vérifié dans ses attributions.', schema: new OA\Schema(type: 'string', format: 'uuid')),
    ])]
    #[OA\Response(response: 200, description: 'Annonces du périmètre autorisé', content: new OA\JsonContent(ref: new Model(type: Feedback::class)))]
    public function list(string $organizationUuid, #[MapQueryString(validationFailedStatusCode: 400)] ?ListingFilterRequest $request = null): JsonResponse
    {
        return $this->respond($this->workflow->listForOrganization($organizationUuid, $request ?? new ListingFilterRequest()));
    }

    #[Route('/organizations/{organizationUuid}/listing-candidates', name: 'candidates', methods: ['GET'])]
    #[OA\Get(summary: 'Lister les unités disponibles et autorisées à devenir une annonce', parameters: [
        new OA\Parameter(name: 'cityUuid', in: 'query', description: 'Ville active pour ADMIN_VILLE; l’UUID est vérifié dans ses attributions.', schema: new OA\Schema(type: 'string', format: 'uuid')),
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
        new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 100)),
    ])]
    #[OA\Response(response: 200, description: 'Biens éligibles dans le périmètre de l’appelant', content: new OA\JsonContent(ref: new Model(type: Feedback::class)))]
    public function candidates(string $organizationUuid, #[MapQueryString(validationFailedStatusCode: 400)] ?ListingCandidateFilterRequest $request = null): JsonResponse
    {
        return $this->respond($this->workflow->listEligibleUnits($organizationUuid, $request ?? new ListingCandidateFilterRequest()));
    }

    #[Route('/units/{unitUuid}/listing', name: 'create', methods: ['POST'])]
    #[OA\Post(summary: 'Créer ou rouvrir un brouillon d’annonce pour une unité')]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: ListingDraftRequest::class)))]
    #[OA\Response(response: 201, description: 'Brouillon créé', content: new OA\JsonContent(ref: new Model(type: Feedback::class)))]
    public function create(string $unitUuid, #[MapRequestPayload] ListingDraftRequest $request): JsonResponse
    {
        return $this->respond($this->workflow->createDraft($unitUuid, $request), 201);
    }

    #[Route('/listings/{uuid}', name: 'show', methods: ['GET'])]
    #[OA\Get(summary: 'Consulter une annonce et son historique interne')]
    #[OA\Response(response: 200, description: 'Annonce et décisions', content: new OA\JsonContent(ref: new Model(type: Feedback::class)))]
    public function show(string $uuid): JsonResponse
    {
        return $this->respond($this->workflow->get($uuid));
    }

    #[Route('/listings/{uuid}', name: 'update', methods: ['PUT'])]
    #[OA\Put(summary: 'Modifier un brouillon ou une annonce en correction')]
    #[OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: ListingDraftRequest::class)))]
    public function update(string $uuid, #[MapRequestPayload] ListingDraftRequest $request): JsonResponse
    {
        return $this->respond($this->workflow->updateDraft($uuid, $request));
    }

    #[Route('/listings/{uuid}/submit', name: 'submit', methods: ['POST'])]
    #[OA\Post(summary: 'Soumettre une annonce au contrôle immobilier')]
    public function submit(string $uuid): JsonResponse { return $this->respond($this->workflow->submit($uuid)); }

    #[Route('/listings/{uuid}/request-corrections', name: 'corrections', methods: ['POST'])]
    #[OA\Post(summary: 'Demander des corrections à l’administrateur de ville')]
    public function requestCorrections(string $uuid, #[MapRequestPayload] ListingDecisionRequest $request): JsonResponse
    {
        return $this->respond($this->workflow->requestCorrections($uuid, $request->reason));
    }

    #[Route('/listings/{uuid}/queue-publication', name: 'queue_publication', methods: ['POST'])]
    #[OA\Post(summary: 'Placer une annonce contrôlée en attente de publication')]
    public function queuePublication(string $uuid): JsonResponse { return $this->respond($this->workflow->queuePublication($uuid)); }

    #[Route('/listings/{uuid}/publish', name: 'publish', methods: ['POST'])]
    #[OA\Post(summary: 'Publier explicitement une annonce vérifiée')]
    public function publish(string $uuid): JsonResponse { return $this->respond($this->workflow->publish($uuid)); }

    #[Route('/listings/{uuid}/reject', name: 'reject', methods: ['POST'])]
    #[OA\Post(summary: 'Rejeter définitivement une annonce avec motif')]
    public function reject(string $uuid, #[MapRequestPayload] ListingDecisionRequest $request): JsonResponse
    {
        return $this->respond($this->workflow->reject($uuid, $request->reason));
    }

    #[Route('/listings/{uuid}/withdraw', name: 'withdraw', methods: ['POST'])]
    #[OA\Post(summary: 'Retirer une annonce publiée avec motif')]
    public function withdraw(string $uuid, #[MapRequestPayload] ListingDecisionRequest $request): JsonResponse
    {
        return $this->respond($this->workflow->withdraw($uuid, $request->reason));
    }

    private function respond(array $data, int $status = 200): JsonResponse
    {
        $feedback = (new Feedback())->setData($data)->setFlushDescription('Annonce traitée.')
            ->autoInitFlush()->setStatus($status);

        return $this->json($feedback, $status);
    }
}
