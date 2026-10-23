<?php

declare(strict_types=1);

namespace App\Controller\Api\Public;

use App\Dto\Feedback;
use App\Dto\Request\Property\CustomerInquiryRequest;
use App\Dto\Response\Property\PublicInquiryReceiptResponse;
use App\Service\Property\CustomerInquiryService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/organizations/{slug}/listings/{unitUuid}/inquiries', name: 'api_public_inquiry_')]
#[OA\Tag(name: 'Public Inquiries', description: 'Demandes publiques relatives à une annonce disponible.')]
final class CustomerInquiryController extends AbstractController
{
    public function __construct(private readonly CustomerInquiryService $inquiries)
    {
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
