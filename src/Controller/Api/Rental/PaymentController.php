<?php

declare(strict_types=1);

namespace App\Controller\Api\Rental;

use App\Dto\Feedback;
use App\Dto\Request\Rental\PaymentCancelRequest;
use App\Dto\Request\Rental\PaymentFilterDto;
use App\Dto\Request\Rental\PaymentRequest;
use App\Entity\Identity\User;
use App\Service\Rental\PaymentService;
use App\Trait\FeedbackTrait;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * PaymentController
 *
 * Package : Rental Management
 *
 * Exposition HTTP des règlements de loyers (lecture seule côté API :
 * un règlement est une trace comptable, il ne se modifie ni ne se supprime
 * pas par l'API).
 *
 * Le contrôleur ne fait que décoder, déléguer au service et sérialiser le
 * `Feedback` retourné. Toute l'autorisation et toute la résolution du
 * périmètre d'Organization sont assurées par `PaymentService` via
 * `SecurityService` : le contrôleur ne consulte jamais la base et ne
 * déduit jamais le tenant depuis l'utilisateur courant.
 *
 * Endpoints :
 * - POST   /api/v1/payments           : enregistrer un règlement
 * - GET    /api/v1/payments/{uuid}    : détails d'un paiement
 * - POST   /api/v1/payments/{uuid}/cancel : annuler un paiement (contre-écriture)
 */
#[Route('/api/v1/payments', name: 'api_payments_')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Payments', description: 'Gestion des règlements de loyers (traces comptables, lecture seule).')]
final class PaymentController extends AbstractController
{
    use FeedbackTrait;

    public function __construct(
        private readonly PaymentService $paymentService
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/payments',
        summary: 'Enregistrer un règlement de loyer',
        description: 'Lie le montant versé à une échéance (Rent) existante. Le service '
            . 'vérifie que l\'échéance appartient à une organisation dans laquelle '
            . 'l\'utilisateur courant dispose du rôle requis.',
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: PaymentRequest::class))),
        responses: [
            new OA\Response(response: 201, description: 'Paiement enregistré', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 403, description: 'Rôle insuffisant sur l\'organisation du bail', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 404, description: 'Échéance de loyer introuvable', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 422, description: 'Données de paiement invalides', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function create(
        #[MapRequestPayload] PaymentRequest $request
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(
                (new Feedback())->setErrorFlushDescription('Utilisateur non authentifié.')->setStatus(401)->autoInitFlush(),
                401
            );
        }

        $feedback = $this->paymentService->recordPayment($request, $user);

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/payments/{uuid}',
        summary: 'Obtenir les détails d\'un paiement',
        responses: [
            new OA\Response(response: 200, description: 'Détails du paiement', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 404, description: 'Paiement introuvable', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        $feedback = $this->paymentService->getPaymentByUuid($uuid);

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('/{uuid}/cancel', name: 'cancel', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/payments/{uuid}/cancel',
        summary: 'Annuler un paiement par contre-écriture',
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: new Model(type: PaymentCancelRequest::class))),
        responses: [
            new OA\Response(response: 201, description: 'Paiement annulé', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 403, description: 'Annulation non autorisée', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 404, description: 'Paiement introuvable', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 409, description: 'Annulation impossible (total deviendrait négatif)', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 422, description: 'Motif requis', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function cancel(
        string $uuid,
        #[MapRequestPayload] PaymentCancelRequest $request
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(
                (new Feedback())->setErrorFlushDescription('Utilisateur non authentifié.')->setStatus(401)->autoInitFlush(),
                401
            );
        }

        $feedback = $this->paymentService->cancelPayment($uuid, $request->reason, $user);

        return $this->json($feedback, $feedback->getStatus());
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/payments',
        summary: 'Lister les paiements avec pagination',
        parameters: [
            new OA\Parameter(name: 'organizationId', in: 'query', schema: new OA\Schema(type: 'string', format: 'uuid'), description: 'Filtrer par organisation (optionnel)'),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1), description: 'Numéro de page'),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20, minimum: 1, maximum: 100), description: 'Éléments par page'),
            new OA\Parameter(name: 'sortBy', in: 'query', schema: new OA\Schema(type: 'string', default: 'paymentDate'), description: 'Champ de tri'),
            new OA\Parameter(name: 'sortOrder', in: 'query', schema: new OA\Schema(type: 'string', enum: ['ASC', 'DESC'], default: 'DESC'), description: 'Ordre de tri'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function list(
        #[MapRequestPayload] PaymentFilterDto $filter
    ): JsonResponse {
        $feedback = $this->paymentService->listPayments($filter);

        return $this->json($feedback, $feedback->getStatus());
    }
}
