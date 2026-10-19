<?php

declare(strict_types=1);

namespace App\Controller\Api\Expense;

use App\Dto\Feedback;
use App\Dto\Request\Expense\CashOpeningBalanceRequest;
use App\Entity\Identity\User;
use App\Service\Expense\CashBalanceService;
use App\Trait\FeedbackTrait;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/treasury/cash-balance', name: 'api_treasury_cash_balance_')]
#[IsGranted('ROLE_USER')]
final class CashBalanceController extends AbstractController
{
    use FeedbackTrait;

    public function __construct(private readonly CashBalanceService $balances)
    {
    }

    #[Route('', name: 'view', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/treasury/cash-balance',
        summary: 'Consulter le solde de caisse à une date de référence',
        parameters: [
            new OA\Parameter(name: 'organizationUuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'cityUuid', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'currency', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['USD', 'CDF'])),
            new OA\Parameter(name: 'asOfDate', in: 'query', description: 'Date métier de référence (incluse). Par défaut : aujourd’hui.', schema: new OA\Schema(type: 'string', format: 'date', example: '2026-10-19')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Solde d’ouverture et solde de caisse calculé'),
            new OA\Response(response: 422, description: 'Périmètre ou date de référence invalide'),
        ],
    )]
    public function view(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json((new Feedback())->setErrorFlushDescription('Utilisateur non authentifié.')->setStatus(401)->autoInitFlush(), 401);
        }
        try {
            $data = $this->balances->view(
                $user,
                (string) $request->query->get('organizationUuid', ''),
                (string) $request->query->get('cityUuid', ''),
                (string) $request->query->get('currency', ''),
                $request->query->get('asOfDate') !== null ? (string) $request->query->get('asOfDate') : null,
            );
        } catch (\InvalidArgumentException $error) {
            return $this->json((new Feedback())->addError('scope', $error->getMessage())->setErrorFlushDescription($error->getMessage())->setStatus(422)->autoInitFlush(), 422);
        }

        return $this->json((new Feedback())->setData($data)->setStatus(200)->autoInitFlush(), 200);
    }

    #[Route('', name: 'update', methods: ['PUT'])]
    public function update(Request $request, #[MapRequestPayload] CashOpeningBalanceRequest $payload): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json((new Feedback())->setErrorFlushDescription('Utilisateur non authentifié.')->setStatus(401)->autoInitFlush(), 401);
        }
        try {
            $data = $this->balances->set(
                $user,
                (string) $request->query->get('organizationUuid', ''),
                (string) $request->query->get('cityUuid', ''),
                $payload,
            );
        } catch (\InvalidArgumentException $error) {
            return $this->json((new Feedback())->addError('balance', $error->getMessage())->setErrorFlushDescription($error->getMessage())->setStatus(422)->autoInitFlush(), 422);
        }

        return $this->json((new Feedback())->setData($data)->setFlushDescription('Solde d’ouverture enregistré.')->setStatus(200)->autoInitFlush(), 200);
    }
}
