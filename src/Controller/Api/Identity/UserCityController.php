<?php

declare(strict_types=1);

namespace App\Controller\Api\Identity;

use App\Dto\Request\Identity\UserCityRequest;
use App\Dto\Request\PaginationQuery;
use App\Service\Identity\UserCityService;
use App\Trait\FeedbackTrait;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * UserCityController
 *
 * Package : Identity & Access — Contrôleur API REST
 *
 * Point d'accès API pour la gestion des périmètres géographiques (villes)
 * attribués aux comptes administrateurs de type ADMIN_VILLE.
 */
#[Route('/api/v1/identity/user-cities', name: 'api_user_cities_')]
#[OA\Tag(name: 'User Cities', description: 'Attribution des périmètres géographiques (villes) aux administrateurs.')]
final class UserCityController extends AbstractController
{
    use FeedbackTrait;

    /**
     * Injecte le service métier dédié à la gestion des périmètres de villes.
     * Permet la consultation et la modification des accès territoriaux.
     */
    public function __construct(
        private readonly UserCityService $userCityService
    ) {
    }

    /**
     * Endpoint API répertoriant les villes attribuées à un utilisateur.
     * Prend l'UUID de l'utilisateur cible pour restituer son périmètre.
     */
    #[Route('/user/{userUuid}', name: 'list_by_user', methods: ['GET'])]
    public function listByUser(
        string $userUuid,
        #[MapQueryString] ?PaginationQuery $query = null
    ): JsonResponse {
        $feedback = $this->userCityService->listByUser($userUuid, $query ?? new PaginationQuery());

        return $this->json($feedback, $feedback->getStatus());
    }

    /**
     * Endpoint API assignant une ville spécifique à un utilisateur.
     * Enregistre l'attribution territoriale dans la table de liaison.
     */
    #[Route('', name: 'assign', methods: ['POST'])]
    public function assign(
        #[MapRequestPayload] UserCityRequest $request
    ): JsonResponse {
        $feedback = $this->userCityService->assignCity($request);

        return $this->json($feedback, $feedback->getStatus());
    }

    /**
     * Endpoint API révoquant l'attribution d'une ville à un utilisateur.
     * Supprime l'enregistrement d'attribution via son identifiant UUID.
     */
    #[Route('/{uuid}', name: 'revoke', methods: ['DELETE'])]
    public function revoke(string $uuid): JsonResponse
    {
        $feedback = $this->userCityService->revokeCity($uuid);

        return $this->json($feedback, $feedback->getStatus());
    }
}
