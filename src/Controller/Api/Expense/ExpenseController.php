<?php

declare(strict_types=1);

namespace App\Controller\Api\Expense;

use App\Dto\Feedback;
use App\Dto\Request\Expense\ExpenseFilterDto;
use App\Dto\Request\Expense\ExpenseRequest;
use App\Service\Expense\ExpenseService;
use App\Trait\FeedbackTrait;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * ExpenseController
 *
 * Package : Expense Management
 *
 * Gestion des dépenses du patrimoine (création, lecture, mise à jour, annulation).
 * Une dépense est toujours rattachée à une ville, qui déduit l'Organization.
 * L'accès est restreint au périmètre Organization → City de l'utilisateur.
 *
 * Endpoints :
 * - POST   /api/v1/expenses           : créer une dépense
 * - GET    /api/v1/expenses           : lister les dépenses (pagination, filtres)
 * - GET    /api/v1/expenses/{uuid}    : détails d'une dépense
 * - PUT    /api/v1/expenses/{uuid}    : modifier une dépense
 * - POST   /api/v1/expenses/{uuid}/cancel : annuler une dépense (contre-écriture)
 */
#[Route('/api/v1/expenses', name: 'api_expenses_')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Expenses', description: 'Gestion des dépenses (ville, catégorie, montant, cible, travailleur).')]
final class ExpenseController extends AbstractController
{
    use FeedbackTrait;

    public function __construct(
        private readonly ExpenseService $expenseService
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/expenses',
        summary: 'Créer une nouvelle dépense',
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: ExpenseRequest::class))),
        responses: [
            new OA\Response(response: 201, description: 'Dépense créée', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 422, description: 'Erreur de validation', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function create(
        #[MapRequestPayload] ExpenseRequest $request
    ): JsonResponse {
        $feedback = $this->expenseService->createExpense($request, $this->getUser());

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/expenses',
        summary: 'Lister les dépenses avec pagination et filtres',
        parameters: [
            new OA\Parameter(name: 'cityIds', in: 'query', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', format: 'uuid')), description: 'Filtrer par villes (UUIDs)'),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1), description: 'Numéro de page'),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20, minimum: 1, maximum: 100), description: 'Éléments par page'),
            new OA\Parameter(name: 'sortBy', in: 'query', schema: new OA\Schema(type: 'string', default: 'expenseDate'), description: 'Champ de tri'),
            new OA\Parameter(name: 'sortOrder', in: 'query', schema: new OA\Schema(type: 'string', enum: ['ASC', 'DESC'], default: 'DESC'), description: 'Ordre de tri'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function list(
        #[MapQueryString] ExpenseFilterDto $filter
    ): JsonResponse {
        $feedback = $this->expenseService->listExpenses(
            cityIds: $filter->cityIds,
            page: $filter->page,
            limit: $filter->limit,
            sortBy: $filter->sortBy,
            sortOrder: $filter->sortOrder
        );

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/expenses/{uuid}',
        summary: 'Obtenir les détails d\'une dépense',
        responses: [
            new OA\Response(response: 200, description: 'Détails de la dépense', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 404, description: 'Dépense introuvable', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        $feedback = $this->expenseService->getExpenseByUuid($uuid);

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}', name: 'update', methods: ['PUT', 'PATCH'])]
    #[OA\Put(
        path: '/api/v1/expenses/{uuid}',
        summary: 'Mettre à jour une dépense',
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: ExpenseRequest::class))),
        responses: [
            new OA\Response(response: 200, description: 'Dépense mise à jour', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 422, description: 'Erreur de validation', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function update(
        string $uuid,
        #[MapRequestPayload] ExpenseRequest $request
    ): JsonResponse {
        $feedback = $this->expenseService->updateExpense($uuid, $request, $this->getUser());

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}/cancel', name: 'cancel', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/expenses/{uuid}/cancel',
        summary: 'Annuler une dépense par contre-écriture',
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: \App\Dto\Request\Expense\ExpenseCancelRequest::class))),
        responses: [
            new OA\Response(response: 201, description: 'Dépense annulée', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 422, description: 'Motif requis', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function cancel(
        string $uuid,
        #[MapRequestPayload] \App\Dto\Request\Expense\ExpenseCancelRequest $request
    ): JsonResponse {
        $feedback = $this->expenseService->cancelExpense($uuid, $request->reason, $this->getUser());

        return $this->json($feedback, $feedback->getStatus());
    }
}