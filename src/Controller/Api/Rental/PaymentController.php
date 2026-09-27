<?php

declare(strict_types=1);

namespace App\Controller\Api\Rental;

use App\Dto\Feedback;
use App\Dto\Request\Rental\PaymentRequest;
use App\Entity\Identity\User;
use App\Service\Rental\PaymentService;
use App\Trait\FeedbackTrait;
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
 * - POST   /api/v1/payments       : enregistrer un règlement
 * - GET    /api/v1/payments/{uuid} : détails d'un paiement
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
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: PaymentRequest::class)),
        responses: [
            new OA\Response(response: 201, description: 'Paiement enregistré', content: new OA\JsonContent(ref: Feedback::class)),
            new OA\Response(response: 403, description: 'Rôle insuffisant sur l\'organisation du bail', content: new OA\JsonContent(ref: Feedback::class)),
            new OA\Response(response: 404, description: 'Échéance de loyer introuvable', content: new OA\JsonContent(ref: Feedback::class)),
            new OA\Response(response: 422, description: 'Données de paiement invalides', content: new OA\JsonContent(ref: Feedback::class)),
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
            new OA\Response(response: 200, description: 'Détails du paiement', content: new OA\JsonContent(ref: Feedback::class)),
            new OA\Response(response: 403, description: 'Accès refusé', content: new OA\JsonContent(ref: Feedback::class)),
            new OA\Response(response: 404, description: 'Paiement introuvable', content: new OA\JsonContent(ref: Feedback::class)),
        ]
    )]
    public function show(string $uuid): JsonResponse
    {
        $feedback = $this->paymentService->getPaymentByUuid($uuid);

        return $this->json($feedback, $feedback->getStatus());
    }
}
