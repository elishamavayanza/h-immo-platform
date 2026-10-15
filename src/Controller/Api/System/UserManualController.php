<?php

declare(strict_types=1);

namespace App\Controller\Api\System;

use App\Dto\Feedback;
use App\Entity\Identity\User;
use App\Service\System\UserManualService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/user-manuals', name: 'api_user_manuals_')]
#[IsGranted('ROLE_USER')]
final class UserManualController extends AbstractController
{
    public function __construct(private readonly UserManualService $manuals)
    {
    }

    #[Route('/current', name: 'current', methods: ['GET'])]
    public function current(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json((new Feedback())->setErrorFlushDescription('Utilisateur non authentifié.')->setStatus(401)->autoInitFlush(), 401);
        }

        $manual = $this->manuals->getCurrentManual();

        return $this->json((new Feedback())->setData($manual['metadata'])->setStatus(200)->autoInitFlush(), 200, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    #[Route('/current/content', name: 'content', methods: ['GET'])]
    public function content(Request $request): BinaryFileResponse
    {
        if (!$this->getUser() instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non authentifié.');
        }

        $manual = $this->manuals->getCurrentManual();
        $response = new BinaryFileResponse($manual['path']);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $disposition = $request->query->getBoolean('download')
            ? ResponseHeaderBag::DISPOSITION_ATTACHMENT
            : ResponseHeaderBag::DISPOSITION_INLINE;
        $response->setContentDisposition($disposition, (string) $manual['metadata']['filename']);

        return $response;
    }

    #[Route('/current/pages/{page<\\d+>}', name: 'page', methods: ['GET'])]
    public function page(int $page): BinaryFileResponse
    {
        if (!$this->getUser() instanceof User) {
            throw new AccessDeniedHttpException('Utilisateur non authentifié.');
        }

        $manualPage = $this->manuals->getCurrentManualPage($page);
        $response = new BinaryFileResponse($manualPage['path']);
        $response->headers->set('Content-Type', 'image/jpeg');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }
}
