<?php

declare(strict_types=1);

namespace App\Controller\Api\System;

use App\Dto\Feedback;
use App\Dto\Request\System\ClientErrorReportRequest;
use App\Service\System\ErrorReportService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Service\System\DateTimeService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[Route('/api/v1/system/client-errors', name: 'api_system_client_errors_')]
#[IsGranted('ROLE_USER')]
final class ClientErrorReportController extends AbstractController
{
    public function __construct(
        private readonly ErrorReportService $errorReportService,
        private readonly DateTimeService $dateTimeService,
        #[Autowire(service: 'limiter.client_error_reports')]
        private readonly RateLimiterFactory $clientErrorLimiter,
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/system/client-errors',
        summary: 'Signaler une erreur inattendue de l’interface',
        description: 'Endpoint réservé aux comptes authentifiés. Limité en fréquence ; les données de formulaire et les jetons ne sont jamais acceptés.',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: ClientErrorReportRequest::class))),
        responses: [
            new OA\Response(response: 202, description: 'Rapport accepté', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 401, description: 'Authentification requise'),
            new OA\Response(response: 429, description: 'Limite de signalements atteinte'),
            new OA\Response(response: 422, description: 'Rapport invalide'),
        ]
    )]
    public function create(#[MapRequestPayload] ClientErrorReportRequest $report, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            throw new AccessDeniedHttpException('Authentification requise.');
        }

        $limit = $this->clientErrorLimiter->create(hash('sha256', $user->getUserIdentifier()))->consume(1);
        if (!$limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - $this->dateTimeService->now()->getTimestamp());
            $feedback = (new Feedback())
                ->setFlush('Limite atteinte')
                ->setFlushDescription('Les signalements sont temporairement limités.')
                ->setStatus(Response::HTTP_TOO_MANY_REQUESTS);

            return $this->json($feedback, Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => (string) $retryAfter]);
        }

        $errorId = $this->errorReportService->reportClientError($report, $user, $request);
        $feedback = (new Feedback())
            ->setFlush('Rapport reçu')
            ->setFlushDescription('Merci. L’incident a été transmis à l’équipe technique.')
            ->setData(['errorId' => $errorId])
            ->setStatus(Response::HTTP_ACCEPTED);

        return $this->json($feedback, Response::HTTP_ACCEPTED);
    }
}
