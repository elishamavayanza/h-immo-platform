<?php

declare(strict_types=1);

namespace App\Controller\Api\Public;

use App\Dto\Feedback;
use App\Dto\Request\Property\CustomerInquiryRequest;
use App\Service\Property\CustomerInquiryService;
use App\Service\Property\PublicInquiryChallengeService;
use App\Service\Property\PublicInquiryTurnstileVerifier;
use App\Service\System\DateTimeService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/organizations/{slug}/listings/{unitUuid}/inquiries', name: 'api_public_inquiry_')]
#[OA\Tag(name: 'Public Inquiries', description: 'Demandes publiques relatives à une annonce disponible.')]
final class CustomerInquiryController extends AbstractController
{
    public function __construct(
        private readonly CustomerInquiryService $inquiries,
        private readonly PublicInquiryChallengeService $challenge,
        private readonly RateLimiterFactory $publicCustomerInquiriesLimiter,
        private readonly RateLimiterFactory $publicInquiryChallengeRequestsLimiter,
        private readonly RateLimiterFactory $publicInquiryChallengeUsesLimiter,
        private readonly PublicInquiryTurnstileVerifier $turnstile,
        private readonly DateTimeService $dateTime,
    ) {
    }

    #[Route('/challenge', name: 'challenge', methods: ['GET'])]
    #[OA\Get(summary: 'Obtenir le jeton anti-robot d’un formulaire de demande', responses: [new OA\Response(response: 200, description: 'Jeton temporaire signé')])]
    public function challenge(string $slug, string $unitUuid, Request $request): JsonResponse
    {
        $limit = $this->publicInquiryChallengeRequestsLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(max(1, $limit->getRetryAfter()->getTimestamp() - $this->dateTime->now()->getTimestamp()), 'Trop de demandes de vérification. Réessayez plus tard.');
        }

        $feedback = (new Feedback())
            ->setData(['token' => $this->challenge->issue($slug, $unitUuid)])
            ->setStatus(200)
            ->autoInitFlush();

        return $this->json($feedback);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Envoyer une demande au sujet d’une annonce publique',
        description: 'Une clé Idempotency-Key UUID est requise. L’organisation est résolue depuis le slug et l’annonce, jamais depuis le corps.',
        parameters: [new OA\Parameter(name: 'Idempotency-Key', in: 'header', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: CustomerInquiryRequest::class))),
        responses: [
            new OA\Response(response: 201, description: 'Demande enregistrée', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 200, description: 'Soumission répétée reconnue', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 404, description: 'Annonce indisponible', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 422, description: 'Champs invalides', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
            new OA\Response(response: 429, description: 'Limite de soumissions atteinte', content: new OA\JsonContent(ref: new Model(type: Feedback::class))),
        ]
    )]
    public function create(
        string $slug,
        string $unitUuid,
        #[MapRequestPayload] CustomerInquiryRequest $requestDto,
        Request $request,
    ): JsonResponse {
        $idempotencyKey = trim((string) $request->headers->get('Idempotency-Key', ''));
        if ($idempotencyKey === '') {
            throw new BadRequestHttpException('L’en-tête Idempotency-Key est obligatoire.');
        }

        if (trim((string) $requestDto->website) !== '') {
            throw new BadRequestHttpException('La demande n’a pas pu être vérifiée.');
        }

        if (!$this->challenge->isValid($requestDto->challengeToken, $slug, $unitUuid)) {
            throw new BadRequestHttpException('La vérification du formulaire a expiré. Rechargez la page puis réessayez.');
        }

        $limit = $this->publicCustomerInquiriesLimiter
            ->create($request->getClientIp() ?? 'unknown')
            ->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(max(1, $limit->getRetryAfter()->getTimestamp() - $this->dateTime->now()->getTimestamp()), 'Trop de demandes ont été envoyées. Réessayez plus tard.');
        }

        if (!$this->turnstile->verify($requestDto->turnstileToken, $request->getClientIp())) {
            throw new BadRequestHttpException('La vérification anti-robot a échoué. Rechargez le formulaire puis réessayez.');
        }

        $tokenUse = $this->publicInquiryChallengeUsesLimiter->create(hash('sha256', $requestDto->challengeToken))->consume();
        if (!$tokenUse->isAccepted()) {
            throw new BadRequestHttpException('Cette vérification a déjà été utilisée. Rechargez le formulaire puis réessayez.');
        }

        $result = $this->inquiries->createPublic($slug, $unitUuid, $requestDto, $idempotencyKey);
        $status = $result['created'] ? 201 : 200;
        $feedback = (new Feedback())
            ->setData($result['receipt'])
            ->setFlushDescription($result['created'] ? 'Votre demande a été transmise.' : 'Cette demande avait déjà été transmise.')
            ->setStatus($status)
            ->autoInitFlush();

        return $this->json($feedback, $status);
    }
}
